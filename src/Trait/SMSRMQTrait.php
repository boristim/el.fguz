<?php

namespace Drupal\elereg\Trait;

use Drupal;
use Exception;
use Throwable;
use DateInterval;
use DateTimeImmutable;
use Drupal\elereg\SMSC_SMPP;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityStorageException;


trait SMSRMQTrait {


  /**
   * @throws EntityStorageException
   * @throws \Exception
   */
  public function sendSMS(int $id): bool {
    $status = FALSE;
    drupal_flush_all_caches();
    try {
      $query = Drupal::database()->query('Select nid from node where nid = :id limit 1', [':id' => $id]);
      $query->fetchAll();
    } catch (Throwable $e) {
      Drupal::logger('SMS')->warning($e->getMessage());
      try {
        $connection = Database::getConnection();
        if ($connection) {
          Drupal::logger('SMS')->info("Database reconnect success: @info", ['@info' => print_r($connection->getConnectionOptions(), 1)]);
        }
      } catch (Throwable $e) {
        Drupal::logger('SMS')->warning("Database reconnect failed: " . $e->getMessage());
        $id = -1;
      }
    }
    if (($id > 0) && ($document = Node::load($id))) {
      $entity_type = $document->getType();
      $phone = '7' . $document->get('registration' == $entity_type ? 'field_tel' : 'field_phone')->getValue()[0]['value'];
      $title = t("SMS @tel for @entity_type(@id)", ['@tel' => $phone, '@id' => $document->id(), '@entity_type' => $entity_type]);
      $message = $this->composeMessage($document);
      $node = Node::create(['type' => 'sms', 'title' => $title]);
      $node->set('body', $message)->set('field_phone', $phone)->set('field_status', FALSE)->set('field_base_node', ['target_id' => $id])->save();
      $smsc = NULL;
      try {
        $smsc = Drupal::service('elereg.smsc_smpp');
      } catch (Throwable $e) {
        Drupal::logger('SMS')->error($e);
      }
      if (!$smsc instanceof SMSC_SMPP) {
        throw new Exception('SMS transport is unreachable');
      }
      try {
        $h24 = time() - ($this->settings['period'] * 60);
        $query = Drupal::entityQuery('node')->condition('type', 'sms')->condition('created', $h24, '>')->condition('field_phone', $phone)->condition('field_status', TRUE)->accessCheck(FALSE);
        $result = $query->execute();
        if (!count($result)) {
          if ($smsc->send_sms($phone, $message, $this->settings['sender'])) {
            $status = TRUE;
          }
          else {
            Drupal::logger('SMS')->warning('Не удалось отправить СМС на %s', ['%s' => $phone]);
          }
        }
        else {
          Drupal::logger('SMS')->warning('СМС на номер %s уже была послана менее чем %min минут(у|ы) назад', ['%s' => $phone, '%min' => $this->settings['period']]);
        }
      } catch (Throwable $e) {
        Drupal::logger('SMS')->error($e);
      } finally {
        $node->set('field_status', $status)->save();
        unset($smsc);
      }
    }
    else {
      Drupal::logger('SMS')->warning("Registration not found: $id");
    }
    return $status;
  }

  private function composeMessage(Node $document): string {
    $methodName = "composeMessage_" . $document->getType();
    $result = 'empty';
    if (method_exists($this, $methodName)) {
      $result = $this->$methodName($document);
    }
    else {
      $result = $this->composeMessage_default();
    }
    //    $result =
    return $result;
    //    return Drupal::transliteration()->transliterate($result, 'ru');
    //    return transliterator_transliterate($result);
  }

  private function composeMessage_default(): string {
    return 'default';
  }

  private function composeMessage_mites(Node $document): string {
    $found = $notFound = [];
    $fields = ['field_vke' => 'ВКЭ', 'field_ikb' => 'ИКБ', 'field_gach' => 'ГАЧ', 'field_mech' => 'МЭЧ'];
    foreach ($fields as $field => $title) {
      $val = $document->get($field)->getValue()[0];
      //      Drupal::logger(__CLASS__)->info('@title: @q', ['@title' => $title, '@q' => var_export($val, 1)]);
      if ($val['value']) {
        $found[] = $title;
      }
      else {
        $notFound[] = $title;
      }
    }
    $notFound = count($notFound) > 0 ? PHP_EOL . (implode(',', $notFound) . ' ' . t(' - not found')) : '';
    $found = count($found) > 0 ? PHP_EOL . (implode(',', $found) . ' ' . t(' - found')) : '';
    return t("Mite number: @no@bad@ok", ['@no' => $document->get('field_mite_reg_no')->getValue()[0]['value'], '@bad' => $notFound, '@ok' => $found]);
  }

  private function composeMessage_registration(Node $document): string {
    $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $document->get('field_data')->getValue()[0]['value']);
    $date = $date->add(new DateInterval('PT5H'));
    $fields = [
      '%fio' => $document->get('field_fio')->getValue()[0]['value'],
      '%phone' => $document->get('field_tel')->getValue()[0]['value'],
      '%date' => $date->format('d/m/Y'),
      '%time' => $date->format('H:i'),
    ];
    $services = [];
    foreach ($document->get('field_services')->getValue() as $service) {
      $term = Term::load($service['target_id']);
      $services[] = '"' . $term->getName() . '"';
    }
    $fields['%service'] = implode(', ', $services);
    return strtr($this->settings['message'], $fields);
  }

}
