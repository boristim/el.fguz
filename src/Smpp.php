<?php


namespace Drupal\elereg;


use Drupal;
use Drupal\Core\Site\Settings;
use Drupal\node\Entity\Node;
use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;

use PhpAmqpLib\Message\AMQPMessage;

class Smpp
{

  private array $settings;
  private array $rmsSettings;

  /**
   * @throws \Exception
   */
  public function __construct()
  {
    $this->settings = Drupal::config('elereg.sms_settings')->getRawData();

    if (($rmsSettings = Settings::get('rabbitmq_credentials')) && is_array($rmsSettings) && (array_key_exists('default', $rmsSettings))) {
      $this->rmsSettings = $rmsSettings['default'];
    } else {
      throw new Exception('RabbitMQ settings not found');
    }
  }

  /**
   * @throws \Exception
   */
  public function sendMessage(Node $registration): void
  {
    $connection = (new AMQPStreamConnection($this->rmsSettings['host'], $this->rmsSettings['port'], $this->rmsSettings['username'], $this->rmsSettings['password'], $this->rmsSettings['vhost']));
    $channel = $connection->channel();
    $channel->queue_declare($this->settings['rmq_name'], false, false, false, false);
    $msg = new AMQPMessage($registration->id());
    $channel->basic_publish($msg, '', $this->settings['rmq_name']);
    $channel->close();
    $connection->close();
  }
}
