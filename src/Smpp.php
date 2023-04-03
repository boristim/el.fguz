<?php


namespace Drupal\elereg;


use Drupal;
use Drupal\Core\Site\Settings;
use Drupal\node\Entity\Node;
use Exception;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

use PhpAmqpLib\Message\AMQPMessage;

class Smpp {

  private array $settings;

  private array $rmsSettings;

  private AMQPChannel $channel;

  private AMQPStreamConnection $rmqConnection;

  /**
   * @throws \Exception
   */
  public function __construct() {
    $this->settings = Drupal::config('elereg.sms_settings')->getRawData();

    if (($rmsSettings = Settings::get('rabbitmq_credentials')) && is_array($rmsSettings) && (array_key_exists('default', $rmsSettings))) {
      $this->rmsSettings = $rmsSettings['default'];
    }
    else {
      throw new Exception('RabbitMQ settings not found');
    }
    $this->rmqConnection = (new AMQPStreamConnection($this->rmsSettings['host'], $this->rmsSettings['port'], $this->rmsSettings['username'], $this->rmsSettings['password'], $this->rmsSettings['vhost']));
    $this->channel = $this->rmqConnection->channel();
    $this->channel->queue_declare($this->settings['rmq_name'], FALSE, FALSE, FALSE, FALSE);
  }

  /**
   * @throws \Exception
   */
  public function sendMessage(Node $document): void {
    $msg = new AMQPMessage($document->id());
    $this->channel->basic_publish($msg, '', $this->settings['rmq_name']);
  }

  /**
   * @throws \Exception
   */
  public function __destruct() {
    $this->channel->close();
    $this->rmqConnection->close();
  }

}
