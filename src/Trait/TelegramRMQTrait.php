<?php

namespace Drupal\elereg\Trait;

use Drupal;

trait TelegramRMQTrait {

  private function sendTg(string $message): void {

    $botConfigFile = realpath(DRUPAL_ROOT . '/../tg') . '/config.yml';
    if (file_exists($botConfigFile) && ($botConf = yaml_parse_file($botConfigFile)) && array_key_exists('chats', $botConf) && is_array($botConf['chats'])) {
      foreach ($botConf['chats'] as $chatId) {
        $this->sendTelegram($message, $botConf['bot_id'], $chatId);
      }
    }
  }

  private function sendTelegram(string $message, string $token, string $chatId): void {
    if ($curl = curl_init()) {
      $query = "https://api.telegram.org/bot$token/sendMessage?disable_web_page_preview=true&chat_id=$chatId&text=" . urlencode($message);
      curl_setopt($curl, CURLOPT_URL, $query);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($curl, CURLOPT_POST, TRUE);
      if (curl_exec($curl) === FALSE) {
        Drupal::logger(__LINE__ . ':' . __CLASS__ . '::' . __FUNCTION__)->notice(curl_error($curl));
      }
      curl_close($curl);
    }
    else {
      Drupal::logger(__LINE__ . ':' . __CLASS__ . '::' . __FUNCTION__)->notice(t("Can't initialize cURL. Is it installed on the server?"));
    }
  }

}
