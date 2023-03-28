<?php

namespace Drupal\elereg\Trait;

use DateTimeImmutable;
use Drupal;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\Entity\Node;
use PhpOffice\PhpSpreadsheet\Reader\Ods;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Reader\Ods as OdsReader;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

trait XlsImport {

  private Worksheet $workSheet;

  private function fieldNames(): array {
    return [
      1 => ['name' => 'field_mite_reg_no', 'type' => 'text', 'require' => TRUE, 'title' => 'Рег. №'],
      2 => ['name' => 'field_reg_time', 'type' => 'date', 'require' => TRUE, 'title' => 'Дата регистрации'],
      3 => ['name' => 'field_reg_time', 'type' => 'time', 'require' => TRUE, 'title' => 'Время регистрации'],
      4 => ['name' => 'title', 'type' => 'text', 'require' => TRUE, 'title' => 'ФИО'],
      5 => ['name' => 'field_born', 'type' => 'dateshort', 'require' => TRUE, 'title' => 'Дата рождения'],
      6 => ['name' => 'field_phone', 'type' => 'phone', 'require' => TRUE, 'title' => '№ телефона'],
      7 => ['name' => 'field_home_address', 'type' => 'text', 'require' => TRUE, 'title' => 'Домашний адрес, № телефона'],
      8 => ['name' => 'field_bite_location', 'type' => 'bite_location', 'require' => TRUE, 'title' => 'Место укуса'],
      9 => ['name' => 'field_bite_place', 'type' => 'cities', 'title' => 'Место пребывания человека'],
      10 => ['name' => 'field_bite_date', 'type' => 'date', 'title' => 'Дата укуса'],
      11 => ['name' => 'field_mite_gender', 'type' => 'mite_gender', 'require' => TRUE, 'title' => 'Вид, род клеща'],
      12 => ['name' => 'field_immunoglobulin', 'type' => 'bool', 'title' => 'Вводился ли иммуноглобулин'],
      13 => ['name' => 'field_vke', 'type' => 'bool', 'title' => 'ВКЭ'],
      14 => ['name' => 'field_ikb', 'type' => 'bool', 'title' => 'ИКБ'],
      15 => ['name' => 'field_gach', 'type' => 'bool', 'title' => 'ГАЧ'],
      16 => ['name' => 'field_mech', 'type' => 'bool', 'title' => 'МЭЧ'],
      17 => ['name' => 'field_date_issued', 'type' => 'date', 'title' => 'Дата выдачи'],
      18 => ['name' => 'field_doctor', 'type' => 'doctors', 'require' => TRUE, 'title' => 'ФИО врача/биолога'],
      19 => ['name' => 'field_check_no', 'type' => 'text', 'require' => TRUE, 'title' => 'Вид оплаты'],
      20 => ['name' => 'field_registrator', 'type' => 'registrators', 'require' => TRUE, 'title' => 'ФИО регистратора'],
    ];
  }

  private function dateTry(string $dateStr, array &$message, bool &$break, string $format = 'Y-m-d\TH:i:s'): ?string {
    $date = explode(' ', $dateStr);
    if (count($date) < 2) {
      $time = '00:00:00';
    }
    else {
      $time = $date[1];
    }
    if ($time && (substr_count($time, ':') == 1)) {
      $dateStr .= ':00';
    }
    else {
      $dateStr .= '00:00:00';
    }
    $res = DateTimeImmutable::createFromFormat('d.m.y H:i:s', $dateStr);
    if (!$res) {
      $res = DateTimeImmutable::createFromFormat('d.m.Y H:i:s', $dateStr);
      if (!$res) {
        $break = TRUE;
        $message[] = t('Unknown date format');
      }
    }

    if ($res instanceof DateTimeImmutable) {
      $res = $res->format('Y-m-d H:i:s');
    }
    else {
      $res = date('Y-m-d H:i:s');
    }

    if ('short' == $format) {
      $res = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $res);
      $format = 'Y-m-d';
    }
    else {
      $res = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $res)->sub(new \DateInterval('PT5H'));
    }
    return $res->format($format);
  }


  /**
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function getTerm(array $field, string $name, array &$message): array {
    $terms = $this->taxonomy->loadByProperties(['vid' => $field['type'], 'name' => $name]);
    if ($terms) {
      return ['target_id' => reset($terms)->id()];
    }
    else {
      $term = $this->taxonomy->create(['vid' => $field['type'], 'name' => $name]);
      $term->save();
      $message[] = t('Created term @type @tid', ['@type' => $field['type'], '@tid' => $term->id()]);
      return ['target_id' => $term->id()];
    }
  }

  /**
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function processCell(mixed $value, array $field, array &$message, bool &$break): string|array {
    static $prevVal;
    $res = '';
    if ($field['type'] == 'date') {
      $res = $this->dateTry($value, $message, $break);
    }
    if ($field['type'] == 'dateshort') {
      $res = $this->dateTry($value, $message, $break, 'short');
    }
    if (('field_reg_time' == $field['name']) && ('time' == $field['type'])) {
      $res = $this->dateTry("$prevVal $value", $message, $break);
    }
    if (!$res) {
      $res = $this->getTerm($field, $value ?: '--//--', $message);
    }
    $prevVal = $value;
    return $res;
  }

  private function phoneTry(string $value, array $field, array &$message, bool &$break): ?string {
    $tel = preg_replace('/\D/', '', $value);
    $tel = ltrim($tel, '7');
    $tel = ltrim($tel, '8');
    $telLen = mb_strlen($tel);
    if ($telLen > 10) {
      $tel = mb_substr($tel, $telLen - 10, 10);
    }
    if ($telLen < 10) {
      $message[] = t('Field @name is too short', ['@name' => $field['title']]);
      $break = TRUE;
    }
    return $tel;
  }

  /**
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function insertMite(array $row): void {
    $this->l(t("Processing: @no", ['@no' => $row[1]]));
    $message = $nodeValues = [];
    $break = FALSE;
    foreach ($this->fieldNames() as $colNo => $field) {
      if (array_key_exists($colNo, $row)) {
        if (!empty($field['require']) && (empty($row[$colNo]))) {
          $message[] .= t('Field @field is required', ['@field' => $field['title']]);
          break;
        }
        $value = $row[$colNo];
        switch ($field['type']) {
          case 'text':
          {
            $val = trim($value);
            break;
          }
          case 'phone':
          {
            $val = $this->phoneTry($value, $field, $message, $break);
            break;
          }
          case'bool':
          {
            $val = empty($value) ? 0 : 1;
            break;
          }
          default:
          {
            $val = $this->processCell($value, $field, $message, $break);
          }
        }
        $nodeValues[$field['name']] = $val;
      }
      else {
        $message[] = t('not all fields');
        break;
      }
    }
    if ((!$break) && (count($nodeValues) == (count($this->fieldNames()) - 1))) {
      $mites = Drupal::entityQuery('node')->condition('type', 'mites')->condition('field_mite_reg_no', $row[1])->accessCheck()->execute();
      if (count($mites)) {
        $miteId = reset($mites);
        $miteNode = Node::load($miteId);
        foreach ($nodeValues as $fieldName => $value) {
          $miteNode->set($fieldName, $value);
        }
      }
      else {
        $nodeValues['type'] = 'mites';
        $miteNode = Node::create($nodeValues);
      }
      $saveState = $miteNode->save();
      if ($saveState == SAVED_UPDATED) {
        $this->l(t('Updated mite: @no', ['@no' => $row[1]]));
      }
      elseif ($saveState == SAVED_NEW) {
        $this->l(t('Created mite: @no', ['@no' => $row[1]]));
      }
    }
    else {
      $this->logger->info('@e', ['@e' => var_export($nodeValues, 1)]);
    }
    if (count($message)) {
      $this->l(implode(', ', $message));
    }
  }

  /**
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function readRow(int $rowNo): void {
    $rowData = [];
    for ($colNo = 1; $colNo <= 20; $colNo++) {
      $rowData[$colNo] = $this->workSheet->getCell([$colNo, $rowNo])->getValue();
    }
    $this->insertMite($rowData);
  }

  private function importMites(string $fileUri): void {
    $this->l(t("File: @file", ['@file' => $fileUri]));
    $filePath = $this->fileSystem->realpath($fileUri);
    $reader = pathinfo($filePath, PATHINFO_EXTENSION) == 'ods' ? (new OdsReader()) : (new XlsxReader());
    try {
      $reader->setReadDataOnly(TRUE);
      $this->workSheet = $reader->load($filePath)->setActiveSheetIndex(0);
      $rowNo = 2;
      while (TRUE) {
        $val = $this->workSheet->getCell([1, $rowNo])->getValue();
        if (isset($val)) {
          $this->readRow($rowNo);
        }
        else {
          break;
        }
        $rowNo++;
      }
    } catch (ReaderException|SpreadsheetException|EntityStorageException $e) {
      $this->l($e->getMessage());
      $this->logger->error('@e', ['@e' => $e->getMessage()]);
    }
    $this->l(t("Processed: @uri", ['@uri' => $fileUri]));
    $this->logger->info('@l', ['@l' => var_export($this->log, 1)]);
  }

}
