<?php

namespace Drupal\elereg\Form;


use DateInterval;
use DateTimeImmutable;
use Drupal;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\elereg\Calendar;
use Drupal\elereg\Trait\RegistrationTrait;
use Drupal\node\Entity\Node;
use Exception;

/**
 * Configure elereg settings for this site.
 */
class SettingsCalendar extends ConfigFormBase {

  use RegistrationTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'elereg_calendar_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['elereg.calendar_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $values = $form_state->getUserInput();

    $weeks = Drupal::service('elereg.calendar')->generateMonth(Calendar::MODE_FULL);

    $week = Drupal::request()->get('week', 0);
    $aWeeks = [];
    foreach ($weeks as $k => $_week) {
      $title = $_week[0]['d'] . '-' . $_week[count($_week) - 1]['d'];
      if ($week == $k) {
        $aWeeks[$k] = '<span>' . $title . '</span>';
      }
      else {
        $aWeeks[$k] = '<a href="?week=' . $k . '">' . $title . '</a>';
      }
    }

    $days = ['all' => 'Вся неделя'];
    foreach ($weeks[$week] as $day) {
      $days[$day['d']] = $day['d'] . ' - ' . $day['w'];
    }
    $header = [
      'date' => ['data' => t('Дата')],
      'time' => ['data' => t('Время')],
      'closed' => ['data' => t('Запись закрыта')],
      'fio' => t('ФИО'),
      'phone' => t('Телефон'),
    ];
    $defServices = [];
    try {
      $defServices = $this->getServices(TRUE);
    } catch (Exception $e) {
      Drupal::logger(__CLASS__)->error($e->getMessage());
    }

    foreach ($defServices as $service) {
      $header['service_' . $service['id']] = ['data' => ['#markup' => '<small>' . $service['short'] . '</small>']];
    }
    $header['description'] = t('Примечание');
    $options = [];
    $cnt = 0;
    foreach ($weeks[$week] as $w) {
      if (isset($w['h']) && is_array($w['h'])) {
        foreach ($w['h'] as $h) {
          $cnt++;
          $_dt = $w['d'] . ' ' . $h['t'];
          $dt = DateTimeImmutable::createFromFormat('d.m.Y H:i:s', $_dt . ':00');
          $dt = $dt->sub(new DateInterval('PT5H'));
          $query = Drupal::entityQuery('node')->condition('type', 'registration')->condition('field_data', $dt->format('Y-m-d\TH:i:s'))->accessCheck(FALSE);
          $result = $query->execute();
          $checked = [];
          $fio = '';
          $phone = '';
          $description = '';
          $selServices = [];
          if (count($result)) {
            $nid = reset($result);
            $reg = Drupal\node\Entity\Node::load($nid);
            $checked = ['checked' => 'checked'];
            $fio = $reg->get('field_fio')->getValue()[0];
            $phone = $reg->get('field_tel')->getString();
            $description = $reg->get('field_description')->getString();
            foreach ($reg->get('field_services')->getValue() as $v) {
              if ($v['target_id']) {
                $selServices[$v['target_id']] = $v['target_id'];
              }
            }
          }

          if ($values) {
            if (isset($values['c'][$_dt])) {
              $checked = ['checked' => 'checked'];
            }
            if (isset($values['f'][$_dt])) {
              $fio = $values['f'][$_dt];
            }
            if (isset($values['p'][$_dt])) {
              $phone = $values['p'][$_dt];
            }
            if (isset($values['d'][$_dt])) {
              $description = $values['d'][$_dt];
            }
            if (isset($values['s'][$_dt])) {
              foreach ($values['s'][$_dt] as $k => $v) {
                $selServices[$k] = $k;
              }
            }
          }

          $option = [
            'date' => $w['d'],
            'time' => $h['t'],
            'closed' => ['data' => ['#type' => 'checkbox', '#name' => 'c[' . $_dt . ']', '#attributes' => $checked,],],
            'fio' => ['data' => ['#type' => 'textfield', '#name' => 'f[' . $_dt . ']', '#value' => $fio]],
            'phone' => ['data' => ['#type' => 'textfield', '#name' => 'p[' . $_dt . ']', '#value' => $phone, '#maxlength' => 15, '#size' => 10]],

          ];
          foreach ($defServices as $service) {
            $checked = isset($selServices[$service['id']]) ? ['checked' => 'checked'] : [];
            $option['service_' . $service['id']] = ['data' => ['#type' => 'checkbox', '#name' => 's[' . $_dt . '][' . $service['id'] . ']', '#attributes' => $checked,],];
          }
          $option['description'] = ['data' => ['#type' => 'textfield', '#name' => 'd[' . $_dt . ']', '#value' => $description]];
          $options[] = $option;
        }
      }
    }
    $form['generate'] = [
      '#type' => 'fieldgroup',
      '#title' => 'Полный календарь',
      'weeks' => [
        '#markup' => implode(' | ', $aWeeks),
      ],
      'days' => [
        '#type' => 'select',
        '#options' => $days,
      ],
      'calendar_table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $options,
      ],
    ];
    $form['#attached']['library'][] = 'elereg/elereg_admin_calendar';
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getUserInput();
    if (isset($values['c']) && is_array($values['c'])) {
      foreach ($values['c'] as $dt => $close) {
        if (empty($values['f'][$dt])) {
          $form_state->setErrorByName("f$dt", "Для записи $dt не указано ФИО");
        }
        if (empty($values['p'][$dt])) {
          $form_state->setErrorByName("p$dt", "Для записи $dt не указан телефон");
        }
        if (!isset($values['s'][$dt])) {
          $form_state->setErrorByName("s1$dt", "Для записи $dt не указаны услуги");
        }
        elseif (!count($values['s'][$dt])) {
          $form_state->setErrorByName("s2$dt", "Для записи $dt не указаны услуги");
        }
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getUserInput();
    if (isset($values['c']) && is_array($values['c'])) {
      foreach ($values['c'] as $dt => $close) {
        $tel = preg_replace('/\D/', '', $values['p'][$dt]);
        $telLen = mb_strlen($tel);
        if ($telLen > 10) {
          $tel = mb_substr($tel, $telLen - 10, 10);
        }
        $date = DrupalDateTime::createFromFormat('d.m.Y H:i:s', $dt . ':00');
        $fio = mb_substr($values['f'][$dt], 0, 254);
        $title = "$tel " . $date->format('Y-m-d H:i');
        $date = $date->sub(new DateInterval('PT5H'));
        $query = Drupal::entityQuery('node')->condition('type', 'registration')->condition('field_data', $date->format('Y-m-d\TH:i:s'))->accessCheck(FALSE);
        $result = $query->execute();
        if (count($result)) {
          $node = Node::load(reset($result));
        }
        else {
          $node = Node::create(['type' => 'registration', 'title' => $title]);
        }
        $node->set('field_data', $date->format('Y-m-d\TH:i:s'))->set('field_tel', $tel)->set('field_fio', $fio);
        $services = [];
        foreach ($values['s'][$dt] as $k => $v) {
          $services[] = ['target_id' => $k];
        }
        $node->set('field_services', $services)->set('field_description', $values['d'][$dt])->save();
      }
    }
    $calendar = (new Calendar())->generateMonth(Calendar::MODE_FULL);
    $weekId = Drupal::request()->get('week', 0);
    $startDay = DateTimeImmutable::createFromFormat('d.m.Y H:i:s', $calendar[$weekId][0]['d'] . ' 00:00:00');
    $startDay = $startDay->sub(new DateInterval('PT5H'));
    $endDay = DateTimeImmutable::createFromFormat('d.m.Y H:i:s', $calendar[$weekId][count($calendar[$weekId]) - 1]['d'] . ' 23:59:59');
    $endDay = $endDay->sub(new DateInterval('PT5H'));
    $query = Drupal::entityQuery('node')->condition('type', 'registration')->condition('field_data', $startDay->format('Y-m-d\TH:i:s'), '>=')->condition(
      'field_data',
      $endDay->format('Y-m-d\TH:i:s'),
      '<='
    )->accessCheck(FALSE);
    $result = $query->execute();
    $valuesC = $values['c'] ?? [];
    if (count($result)) {
      foreach ($result as $nid) {
        $node = Node::load($nid);
        $cdt = DrupalDateTime::createFromFormat('Y-m-d\TH:i:s', $node->get('field_data')->getValue()[0]['value']);
        $cdt = $cdt->add(new DateInterval('PT5H'));
        if (!array_key_exists($cdt->format('d.m.Y H:i'), $valuesC)) {
          $fio = $node->get('field_fio')->getValue();
          if (is_array($fio)) {
            $fio = $fio[0]['value'];
          }
          else {
            $fio = '';
          }
          $tel = $node->get('field_tel')->getValue();
          if (is_array($tel)) {
            $tel = $tel[0]['value'];
          }
          $desc = $node->get('field_description')->getValue();
          if (is_array($desc)) {
            $desc = $desc[0]['value'];
          }
          else {
            $desc = '';
          }
          $message = 'Удалена регистрация ' . $cdt->format('d.m.Y H:i') . " $fio  $tel $desc";
          Drupal::messenger()->addMessage($message);
          Drupal::logger('Calendar')->emergency($message);
          $node->delete();
        }
      }
    }
    parent::submitForm($form, $form_state);
  }

}
