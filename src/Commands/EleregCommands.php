<?php

namespace Drupal\elereg\Commands;

use Drupal;


use Drupal\node\Entity\Node;
use Exception;
use Throwable;

use Drupal\Core\Site\Settings;
use Drush\Commands\DrushCommands;
use PhpAmqpLib\Message\AMQPMessage;
use Drupal\elereg\Trait\SMSRMQTrait;
use Drupal\elereg\Trait\TelegramRMQTrait;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class EleregCommands extends DrushCommands {

  use SMSRMQTrait;
  use TelegramRMQTrait;

  private array $settings;

  private mixed $rmqSettings;

  /**
   * @throws Exception
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
   * @command elereg:listen
   * @aliases erl
   * @usage elereg:listen [--force]
   * @description Listener for RabbitMQ messages
   * @throws \Exception
   */
  public function listenRMQ(): void {
    $connection = (new AMQPStreamConnection($this->rmqSettings['host'], $this->rmqSettings['port'], $this->rmqSettings['username'], $this->rmqSettings['password'], $this->rmqSettings['vhost']));
    $channel = $connection->channel();
    $channel->queue_declare($this->settings['rmq_name'], FALSE, FALSE, FALSE, FALSE);
    $this->output->writeln(date('Y-m-d H:i:s') . ": [*] Waiting for messages. To exit press CTRL+C");
    $callback = function (AMQPMessage $msg) {
      $nodeId = intval($msg->body);
      try {
        $document = Node::load($nodeId);
        $message = $this->composeMessage($document);
        $this->sendTg($message);
      } catch (Throwable $e) {
        Drupal::logger('SMS:Telegram')->error($e->getMessage());
      }
      try {
        if ($this->sendSMS($nodeId)) {
          $this->output->writeln(date('Y-m-d H:i:s') . ': [x] Received ' . $msg->body);
        }
        else {
          $this->output->writeln(date('Y-m-d H:i:s') . ': [!] Failed ' . $msg->body);
        }
      } catch (Throwable $e) {
        Drupal::logger('SMS')->error($e);
      }
    };
    $channel->basic_consume($this->settings['rmq_name'], '', FALSE, TRUE, FALSE, FALSE, $callback);

    while ($channel->is_open()) {
      $channel->wait();
      sleep(1);
    }

    $channel->close();
    $connection->close();
  }

}
