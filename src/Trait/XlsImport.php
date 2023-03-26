<?php

namespace Drupal\elereg\Trait;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

trait XlsImport {

  private Worksheet $workSheet;

  private function readRow(int $rowNo): void {
    $rowData = [];
    for ($colNo = 1; $colNo <= 20; $colNo++) {
      $rowData[$colNo] = $this->workSheet->getCell([$colNo, $rowNo])->getValue();
    }
    $this->l($rowData);
  }

  private function importMites(string $fileUri): void {
    $this->l("File: $fileUri");
    $filePath = $this->fileSystem->realpath($fileUri);
    $reader = new XlsxReader();
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
    } catch (ReaderException|SpreadsheetException $e) {
      $this->l($e->getMessage());
      $this->logger->error('@e', ['@e' => $e->getMessage()]);
    }
    $this->l("Processed $filePath");
    $this->logger->info('@l', ['@l' => var_export($this->log, 11)]);
  }

}
