<?php

namespace Drupal\elereg;

class PhoneUtils {

  public function normalize(string $string): string {
    $tel = preg_replace('/\D/', '', $string);
    $telLen = mb_strlen($tel);
    if ($telLen > 10) {
      $tel = mb_substr($tel, $telLen - 10, 10);
    }
    return $tel;
  }

}
