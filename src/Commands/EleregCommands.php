<?php

namespace Drupal\elereg\Commands;

use DateInterval;
use DateTimeImmutable;
use Drupal;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Site\Settings;
use Drupal\elereg\SMSC_SMPP;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drush\Commands\DrushCommands;
use Error;
use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class EleregCommands extends DrushCommands {


  private array $settings;

  private mixed $rmqSettings;

  /**
   * @throws \Exception
   */
  public function __construct() {
    parent::__construct();
    $this->settings = Drupal::config('elereg.sms_settings')->getRawData();
    if (($rmqSettings = Settings::get('rabbitmq_credentials')) && is_array($rmqSettings) && (array_key_exists('default', $rmqSettings))) {
      $this->rmqSettings = $rmqSettings['default'];
    }
    else {
      throw new Exception('RabbitMQ settings not found');
    }
  }

  /**
   *
   *
   * @command elereg:listen
   * @aliases erl
   * @usage elereg:listen [--force]
   * @description Listener for RabbitMQ messages
   * @throws \Exception
   */
  public function listenRMQ() {
    $connection = (new AMQPStreamConnection($this->rmqSettings['host'], $this->rmqSettings['port'], $this->rmqSettings['username'], $this->rmqSettings['password'], $this->rmqSettings['vhost']));
    $channel = $connection->channel();
    $channel->queue_declare($this->settings['rmq_name'], FALSE, FALSE, FALSE, FALSE);
    $this->output->writeln(" [*] Waiting for messages. To exit press CTRL+C");
    $callback = function (AMQPMessage $msg) {
      try {
        if ($this->sendSMS(intval($msg->body))) {
          $this->output->writeln(' [x] Received ' . $msg->body);
        }
        else {
          $this->output->writeln(' [!] Failed ' . $msg->body);
        }
      } catch (Exception $e) {
        Drupal::logger('SMS')->error($e->getMessage());
      }
    };
    $channel->basic_consume($this->settings['rmq_name'], '', FALSE, TRUE, FALSE, FALSE, $callback);

    while ($channel->is_open()) {
      $channel->wait();
      sleep(5);
    }

    $channel->close();
    $connection->close();
  }

  /**
   * @throws EntityStorageException
   * @throws \Exception
   */
  public function sendSMS(int $id): bool {
    $status = FALSE;
    if ($registration = Node::load($id)) {
      $phone = '7' . $registration->get('field_tel')->getValue()[0]['value'];
      $title = t("SMS @tel, для регистрации #@id", ['@tel' => $phone, '@id' => $registration->id()]);
      $message = $this->composeMessage($registration);
      $node = Node::create(['type' => 'sms', 'title' => $title]);
      $node->set('body', $message)->set('field_phone', $phone)->set('field_status', FALSE)->save();
      $smsc = NULL;
      try {
        $smsc = Drupal::service('elereg.smsc_smpp');
      } catch (Exception $e) {
        Drupal::logger('SMS')->error($e->getMessage());
      } catch (Error $e) {
        Drupal::logger('SMS')->error($e->getMessage());
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
            Drupal::logger('SMS')->error('Ошибка отправки СМС на %s', ['%s' => $phone]);
          }
        }
        else {
          Drupal::logger('SMS')->info('СМС на номер %s послана менее чем минуту назад', ['%s' => $phone]);
        }
      } catch (Exception $e) {
        Drupal::logger('SMS')->error($e->getMessage());
      } catch (Error $e) {
        Drupal::logger('SMS')->error($e->getMessage());
      } finally {
        unset($smsc);
      }

      $node->set('field_status', $status)->save();
    }
    return $status;
  }

  private function composeMessage(Node $registration): string {
    $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $registration->get('field_data')->getValue()[0]['value']);
    $date = $date->add(new DateInterval('PT5H'));
    $fields = [
      '%fio' => $registration->get('field_fio')->getValue()[0]['value'],
      '%phone' => $registration->get('field_tel')->getValue()[0]['value'],
      '%date' => $date->format('d/m/Y'),
      '%time' => $date->format('H:i'),
    ];
    $services = [];
    foreach ($registration->get('field_services')->getValue() as $service) {
      $term = Term::load($service['target_id']);
      $services[] = '"' . $term->getName() . '"';
    }
    $fields['%service'] = implode(', ', $services);
    return strtr($this->settings['message'], $fields);
  }

}
