<?php

namespace Drupal\elereg\Controller;


use Drupal;
use Drupal\node\Entity\Node;
use Drupal\elereg\{Mites, Smpp};
use Drupal\Core\Controller\ControllerBase;
use Drupal\elereg\Trait\{EleregTrait, RegistrationTrait};
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for elereg routes.
 */
class EleregController extends ControllerBase {

  use EleregTrait;
  use RegistrationTrait;

  const VOC_SERVICES = 'services';

  /**
   * Builds the response.
   */
  public function build(): array {
    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

  /**
   * @return array
   */
  public function main(): array {
    $build['content'] = [
      '#type' => 'item',
      '#markup' => '<div class="elereg-throbber"></div>',
      '#attached' => [
        'drupalSettings' => [
          'elereg' => ['endPoint' => '/elereg/ajax', 'rootElement' => '#page-wrapper'],
        ],
      ],
    ];
    return $build;
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  public function ajax(Request $request): JsonResponse {
    $data = [];
    /**
     * @var \Drupal\elereg\Calendar $calendar
     */
    $calendar = Drupal::service('elereg.calendar');
    if ($request->getMethod() == 'GET') {
      $data['dates'] = $calendar->generateMonth();
      $data['services'] = $this->getServices();
    }
    if ($request->getMethod() == 'POST') {
      $values = json_decode($request->getContent(), TRUE);

      $ret = $this->validateRegistration($values);
      if ('ok' == $ret['status']) {
        $node = $this->saveRegistrationNode($values, $ret, TRUE);
        /**
         * @var Smpp $smpp
         */
        $smpp = Drupal::service('elereg.smpp');
        $smpp->sendMessage($node);
      }
      $data = $ret;
    }
    return (new JsonResponse())->setData($data);
  }

  public function mitesXls(Request $request): Response|NotFoundHttpException {
    /**
     * @var $mites Mites
     */
    $mites = Drupal::service('elereg.mites');

    if ($request->getMethod() == 'GET') {
      $response = $mites->exportXls($request);
    }
    if ($request->getMethod() == 'POST') {
      $response = $mites->importXls($request);
    }
    if (isset($response) && ($response instanceof Response)) {
      return $response;
    }
    else {
      return new NotFoundHttpException();
    }
  }

  /**
   * @throws \Exception
   */
  public function mitesSMS(Request $request): Response {
    $items = $request->get('s');
    $sent = [];
    if (is_array($items)) {
      /**
       * @var Smpp $smpp
       */
      $smpp = Drupal::service('elereg.smpp');
      foreach ($items as $regNo => $state) {
        if ($regNo == $state) {
          if (($miteIds = Drupal::entityQuery('node')->condition('type', 'mites')->condition('field_mite_reg_no', $regNo)->accessCheck(FALSE)->execute()) && ($mite = Node::load(reset($miteIds)))) {
            $smpp->sendMessage($mite);
            $sent[] = "s[$regNo]";
          }
        }
      }
    }
    return (new JsonResponse())->setData(['log' => $items, 'sent' => $sent]);
  }

  public function mitesSMSGet(Request $request): Response {
    $regNo = $request->get('n');
    if (($miteIds = Drupal::entityQuery('node')->condition('type', 'mites')->condition('field_mite_reg_no', $regNo)->accessCheck(FALSE)->execute())
      && (($smsIds = Drupal::entityQuery('node')->condition('type', 'sms')->condition('field_base_node', reset($miteIds))->sort('changed','DESC')->accessCheck(FALSE)->execute()))
      && ($sms = Node::load(reset($smsIds)))) {
      $dt = $sms->get('changed')->getValue()[0]['value'];
      $dt = Drupal\Core\Datetime\DrupalDateTime::createFromTimestamp($dt)->format('d.m H:i:s');
      $st = boolval($sms->get('field_status')->getValue()[0]['value']);
    }
    else {
      $dt = '';
      $st = FALSE;
    }
    return (new JsonResponse())->setData(['dt' => $dt, 'st' => $st]);
  }

}
