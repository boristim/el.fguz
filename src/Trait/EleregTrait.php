<?php

namespace Drupal\elereg\Trait;

use Drupal;
use DateInterval;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\Entity\Node;
use InvalidArgumentException;

trait EleregTrait
{

    /**
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    private function validateRegistration(array $values): array
    {
        $ret = ['status' => 'ok'];
        if (!isset($values['Services'], $values['tel'], $values['fio'], $values['Weeks'], $values['Hours'])) {
            $ret['error'] = 'Ошибка сохранения';
            $ret['errorMessage'] = 'Not all required data';
            $ret['data'] = $values;
            $ret['status'] = 'error';
            return $ret;
        }
        try {
            $date = DrupalDateTime::createFromFormat('d.m.Y H:i:s', $values['Weeks'][0] . ' ' . $values['Hours'][0] . ':00');
        } catch (InvalidArgumentException $e) {
            $ret['error'] = 'Плохой формат даты';
            $ret['errorMessage'] = $e->getMessage();
            $ret['status'] = 'error';
            return $ret;
        }
        $tel = preg_replace('/\D/', '', $values['tel']);
        $telLen = mb_strlen($tel);
        if ($telLen > 10) {
            $tel = mb_substr($tel, $telLen - 10, 10);
        }
        $fio = mb_substr($values['fio'], 0, 254);
        $title = "$tel " . $date->format('Y-m-d H:i');
        $date = $date->sub(new DateInterval('PT5H'));
        if (!$this->checkForBusyRegistration($date, $ret)) {
            return $ret;
        }

        if (!$this->checkPhoneForOneDay($tel, $date, $ret)) {
            return $ret;
        }

        if (!$this->checkPhoneForThreePerWeek($tel, $date, $ret)) {
            return $ret;
        }
        $ret['status'] = 'ok';
        $ret['up']['date'] = $date;
        $ret['up']['title'] = $title;
        $ret['up']['tel'] = $tel;
        $ret['up']['fio'] = $fio;
        return $ret;
    }

    private function checkPhoneForOneDay(string $tel, DrupalDateTime $date, array &$ret): bool
    {
        $query = Drupal::entityQuery('node')->condition('type', 'registration')->condition('field_tel', $tel)->condition('field_data', $date->format('Y-m-d') . '%', 'LIKE')->accessCheck(false);
        $result = $query->execute();
        $ret['checkInfo'] = $result;
        if (count($result)) {
            $ret['message'] = 'На этот день вы уже зарегистрированы';
            $ret['info'] = $result;
            $ret['status'] = 'warning';
            return false;
        }
        return true;
    }

    private function checkForBusyRegistration(DrupalDateTime $date, array &$ret): bool
    {
        $query = Drupal::entityQuery('node')->condition('type', 'registration')->condition('field_data', $date->format('Y-m-d\TH:i:s'))->accessCheck(false);
        $result = $query->execute();
        $ret['checkInfo'] = $result;
        if (count($result)) {
            $ret['message'] = 'К сожалению, данное время уже занято';
            $ret['info'] = $result;
            $ret['status'] = 'warning';
            return false;
        }
        return true;
    }

    /**
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    private function checkPhoneForThreePerWeek(string $tel, DrupalDateTime $date, array &$ret): bool
    {
        $day = $date->getTimestamp();
        $curDay = intval(date('w', $day)) - 1;
        $firstWeekDay = DrupalDateTime::createFromTimestamp(strtotime('-' . $curDay . ' days', $day))->format('Y-m-d') . '00:00:00';
        $lastWeekDay = DrupalDateTime::createFromTimestamp(strtotime('+' . (6 - $curDay) . ' days', $day))->format('Y-m-d') . '23:59:59';
        $query = Drupal::entityQuery('node')->condition('type', 'registration')->condition('field_tel', $tel)->condition('field_data', $firstWeekDay, '>=')->condition(
            'field_data',
            $lastWeekDay,
            '<='
        )->accessCheck(false);

        $services = [];
        $result = $query->execute();
        foreach ($result as $nid) {
            $node = Node::load($nid);
            foreach ($node->get('field_services')->getValue() as $v) {
                if ($v['target_id']) {
                    $services[$v['target_id']] = $v['target_id'];
                }
            }
        }
        $availServices = $this->getServices();
        if (count($services) >= count($availServices)) {
            $ret['message'] = 'На указанной неделе вы уже зарегистрированы на все доступные услуги(' . count($services) . ')';
            $ret['info'] = $services;
            $ret['status'] = 'warning';
            return false;
        }
        return true;
    }

}
