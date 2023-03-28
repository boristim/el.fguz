<?php

namespace Drupal\elereg;


use Drupal;

use Psr\Log\LoggerInterface;
use Drupal\file\FileRepository;
use Drupal\elereg\Trait\{XlsImport, XlsExport};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\{BinaryFileResponse, JsonResponse, Request, Response};
use Drupal\Core\{Entity\EntityStorageException, Entity\EntityStorageInterface, File\FileSystem, File\FileSystemInterface, Render\Renderer};


class Mites {

  use XlsExport;
  use XlsImport;

  private array $log;

  private LoggerInterface $logger;

  private EntityStorageInterface $taxonomy;

  /**
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(private readonly Renderer $renderer, private readonly FileSystem $fileSystem, private readonly FileRepository $fileRepository) {
    $this->logger = Drupal::logger(__CLASS__);
    $this->taxonomy = Drupal::entityTypeManager()->getStorage('taxonomy_term');
  }

  public function exportXls(Request $request): Response|NotFoundHttpException {
    if ($uri = $this->generateXls($request)) {
      $fn = str_replace(['public:/', '/' . date('Y-m') . '/'], '', $uri);
      $headers = [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment;filename="' . $fn . '"',
      ];
      return (new BinaryFileResponse($uri, 200, $headers, TRUE));
    }
    return new NotFoundHttpException();
  }

  public function importXls(Request $request): Response {
    $resp['dbg'] = __FILE__;
    /**
     * @var $files \Symfony\Component\HttpFoundation\File\UploadedFile
     */
    if ($files = $request->files->get('files')) {
      $directory = 'public://' . date('Y-m') . '/import';
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      try {
        $file = $this->fileRepository->writeData($files->getContent(), $directory . '/' . $files->getClientOriginalName());
        $this->importMites($file->getFileUri());
      } catch (EntityStorageException $e) {
        $this->logger->error($e->getMessage());
      }
      $resp['log'] = $this->log;
    }
    return (new JsonResponse())->setData($resp);
  }

  private function l(mixed $msg): void {
    $this->log[] = ['dt' => date('H:i:s'), 'msg' => $msg];
  }

}
