<?php

namespace Drupal\elereg\Trait;


use Exception;
use Drupal\views\Views;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\HttpFoundation\Request;
use PhpOffice\PhpSpreadsheet\Reader\Html as HtmlReader;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;


trait XlsExport {

  private function generateXls(Request $request): string {
    if ($view = Views::getView('mites')) {
      $renderable = $view->buildRenderable('page_1');
      $html = '';
      try {
        $html = $this->renderer->render($renderable);
      } catch (Exception $e) {
        $this->logger->error($e->getMessage());
      }
      if (preg_match('/<div class="view-content">(.*)<\/div>/isU', $html, $matches)) {
        $html = $matches[1];
      }
      $directory = 'public://' . date('Y-m') . '/report';
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      if (($get = $request->query->all()) && array_key_exists('dt', $get) && is_array($get['dt'])) {
        $dt = $get['dt'];
      }
      else {
        $dt = ['min' => ['date' => date('Y-m-d')], 'max' => ['date' => date('Y-m-d')]];
      }
      $resultXmlFile = sprintf('%s/%s_%s', $directory, $dt['min']['date'], $dt['max']['date']) . '.xlsx';
      $reader = new HtmlReader();
      try {
        $spreadsheet = @$reader->loadFromString($html);
        $writer = new XlsxWriter($spreadsheet);
        $writer->save($resultXmlFile);
      } catch (Exception $e) {
        $this->logger->error($e->getMessage());
      }
      return $resultXmlFile;
    }
    return '';
  }

}
