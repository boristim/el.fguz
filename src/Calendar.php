<?php

namespace Drupal\elereg;


use Drupal;
use DateInterval;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\Entity\Node;

/**
 * Service description.
 */
class Calendar
{

    const MODE_NORMAL = 1;

    const MODE_FULL = 2;

    const SECONDS_IN_DAY = 86400;

    const SECONDS_IN_WEEK = 604800;

    private int $generateMode = self::MODE_NORMAL;

    private array $settings;

    private array $busyTickets = [];

    private array $specialDays = [];

    public function __construct()
    {
        $this->settings = Drupal::config('elereg.settings')->getRawData();
    }

    private function generateDay(string $day, string $genHours = ''): array
    {
        $ret = [];
        $formatYmd = 'Y-m-d';
        $formatHis = 'H:i:s';
        $beginDay = DrupalDateTime::createFromTimestamp($day)->format($formatYmd);
        $formatYmdHis = "$formatYmd $formatHis";
        $begin = DrupalDateTime::createFromFormat($formatYmdHis, $beginDay . ' ' . $this->settings['work_from'])->getTimestamp();
        if ($genHours) {
            $end = DrupalDateTime::createFromFormat($formatYmdHis, $beginDay . ' ' . $genHours)->getTimestamp();
        } else {
            if (date('w', $day) == 5) {
                if ($this->generateMode == self::MODE_FULL) {
                    $end = DrupalDateTime::createFromFormat($formatYmdHis, $beginDay . ' ' . $this->settings['work_end'])->getTimestamp();
                } else {
                    $end = DrupalDateTime::createFromFormat($formatYmdHis, $beginDay . ' ' . $this->settings['work_end_friday'])->getTimestamp();
                }
            } else {
                $end = DrupalDateTime::createFromFormat($formatYmdHis, $beginDay . ' ' . $this->settings['work_end'])->getTimestamp();
            }
        }
        $curTime = time();
        $lunchFrom = str_replace(':', '', $this->settings['lunch_from']);
        $lunchTo = str_replace(':', '', $this->settings['lunch_end']);
        for ($timeStamp = $begin; $timeStamp < $end; $timeStamp += ($this->settings['interval'] * 60)) {
            $tmp = [];
            $time = DrupalDateTime::createFromFormat($formatYmdHis, $beginDay . ' ' . DrupalDateTime::createFromTimestamp($timeStamp)->format($formatHis));
            $_time = $time->format('Hi') . '00';
            if (($_time >= $lunchFrom) && ($_time <= $lunchTo)) {
                if ($this->generateMode == self::MODE_NORMAL) {
                    continue;
                }
            }
            $tmp['t'] = $time->format('H:i');
            $tmp['s'] = ($curTime - 10) < $time->getTimestamp();
            if (isset($this->busyTickets[$time->getTimestamp()])) {
                if ($this->generateMode == self::MODE_NORMAL) {
                    $tmp['s'] = false;
//                    $tmp['dbg'] = $this->busyTickets[$time->getTimestamp()];
                }
            }
            $ret[] = $tmp;
        }
        return $ret;
    }

    /**
     * @param $day
     *
     * @return array
     */
    private function generateWeek($day): array
    {
        $ret = [];
        $endTime = $this->settings['work_end'];
        if ($this->generateMode == self::MODE_NORMAL) {
            if (intval(date('w')) == 5) {
                $endTime = $this->settings['work_end_friday'];
            }
        }
        $cDayEnd = DrupalDateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' ' . $endTime);
        $curDay = intval(date('w', $day)) - 1;
        $firstWeekDay = strtotime('-' . $curDay . ' days', $day);
        $lastWeekDay = strtotime('+' . (6 - $curDay) . ' days', $day);
        $curTime = DrupalDateTime::createFromTimestamp(time());
        if (self::MODE_FULL == $this->generateMode) {
            $firstWeekDay = strtotime('-' . 0 . ' days', $day);
        }
        for ($_dayOfWeek = $firstWeekDay; $_dayOfWeek <= $lastWeekDay; $_dayOfWeek += self::SECONDS_IN_DAY) {
            $dayOfWeek = DrupalDateTime::createFromTimestamp($_dayOfWeek);
            $tmp['d'] = $dayOfWeek->format('d.m.Y');
            $tmp['w'] = $dayOfWeek->format('l');
            $tmp['s'] = $curTime->getTimestamp() <= $dayOfWeek->getTimestamp();
            if ($this->generateMode === self::MODE_NORMAL) {
                if (($curTime->format('Y-m-d') == $dayOfWeek->format('Y-m-d')) && ($curTime->getTimestamp() > $cDayEnd->getTimestamp())) {
                    $tmp['s'] = false;
                }
                if (($dayOfWeek->format('w') > 5) || ($dayOfWeek->format('w') < 1)) {
                    $tmp['s'] = false;
                }
            }
            $genHours = '';
            if (isset($this->specialDays[$tmp['d']])) {
                $tmp['s'] = true;
                if ($this->specialDays[$tmp['d']]) {
                    $genHours = $this->specialDays[$tmp['d']];
                } else {
                    if ($this->generateMode == self::MODE_NORMAL) {
                        $tmp['s'] = false;
                    }
                }
            }
            if ($tmp['s']) {
                $tmp['h'] = $this->generateDay($dayOfWeek->getTimestamp(), $genHours);
            }
            if ((self::MODE_FULL == $this->generateMode) && (!$tmp['s'])) {
                continue;
            }
            $ret[] = $tmp;
        }
        return $ret;
    }

    /**
     * @param int $mode
     *
     * @return array
     */
    public function generateMonth(int $mode = self::MODE_NORMAL): array
    {
        //        $this->generateMode = self::MODE_FULL;
        $this->generateMode = $mode;
        $weeks = [];
        if ($this->generateMode === self::MODE_NORMAL) {
            $this->getBusyTickets();
            $this->getSpecialDays();
//            $weeks['bdate'] = $this->busyTickets;
        }

        $curTime = time();
        $totalWeeks = $this->settings['weeks'];
        if ($this->generateMode == self::MODE_FULL) {
            $totalWeeks = 7;
            $prevDay = intval(date('w', time())) - 1;
            $curTime = strtotime('-' . $prevDay . ' days', time());
        }
        for ($day = 0; $day < (self::SECONDS_IN_WEEK * $totalWeeks); $day += self::SECONDS_IN_WEEK) {
            $weeks[] = $this->generateWeek($curTime + $day);
        }

        return $weeks;
    }

    private function getBusyTickets(): void
    {
        $_fromDate = (new \DateTimeImmutable())->sub(new DateInterval('PT5H'));
        $fromDate = $_fromDate->format('Y-m-d\TH:i:s');

        //        $query = Drupal::entityQuery('node')->condition('type', 'registration')->condition('field_data', $fromDate, '=');
        $query = Drupal::entityQuery('node')->condition('type', 'registration')->condition('field_data', $fromDate, '>')->accessCheck(false);

        foreach ($query->execute() as $row) {
            $node = Node::load($row);
            $cdt = (\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $node->get('field_data')->getValue()[0]['value']))->add(new DateInterval('PT5H'));


            $this->busyTickets[$cdt->getTimestamp()] = 1;
            //            $this->busyTickets[$cdt->getTimestamp()] = ['dd' => $cdt0, 'ddd' => $cdt->format('Y-m-d H:i:s'), 'dddd' => $cdt->format('Y-m-d H:i:s')];
        }
        $this->dump = $this->busyTickets;
    }

    private function getSpecialDays(): void
    {
        $fromDate = DrupalDateTime::createFromTimestamp(time())->format('Y-m-d');
        $query = Drupal::entityQuery('node')->condition('type', 'holidays')->condition('field_spec_data', $fromDate, '>')->accessCheck(false);
        foreach ($query->execute() as $row) {
            $node = Node::load($row);
            $cdt = DrupalDateTime::createFromFormat('Y-m-d H:i:s', $node->get('field_spec_data')->getValue()[0]['value'] . ' 00:00:00');
            $this->specialDays[$cdt->format('d.m.Y')] = $this->getSpecialDayEnd($node->get('field_day_type')->getValue()[0]['value']);
        }
    }

    private function getSpecialDayEnd(int $dayType): string
    {
        $ret = match ($dayType) {
            2 => '',
            4, 16 => $this->settings['work_end_friday'],
            default => $this->settings['work_end'],
        };
        if ($this->generateMode == self::MODE_FULL) {
            $ret = $this->settings['work_end'];
        }
        return $ret;
    }


}
