<?php

namespace Drupal\elereg\Controller;


use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\elereg\Smpp;
use Drupal\elereg\Trait\EleregTrait;
use Drupal\elereg\Trait\RegistrationTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for elereg routes.
 */
class EleregController extends ControllerBase
{

    use EleregTrait;
    use RegistrationTrait;

    const VOC_SERVICES = 'services';

    /**
     * Builds the response.
     */
    public function build(): array
    {
        $build['content'] = [
            '#type' => 'item',
            '#markup' => $this->t('It works!'),
        ];

        return $build;
    }
// http://fguz-tyumen.ru/about/plat-uslugi/
    /**
     * @return array
     */
    public function main(): array
    {
        $build['content'] = [
            '#type' => 'item',
            '#markup' => '<div class="elereg-throbber"></div>',
            '#attached' => [
                'drupalSettings' => [
                    'elereg' => ['endPoint' => '/elereg/ajax', 'rootElement' => '#page-wrapper'],
//                    'elereg' => ['endPoint' => '/elereg/ajax', 'rootElement' => '.column.main-content'],
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
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public function ajax(Request $request): JsonResponse
    {
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
            $values = json_decode($request->getContent(), true);

            $ret = $this->validateRegistration($values);
            if ('ok' == $ret['status']) {
                $node = $this->saveRegistrationNode($values, $ret, true);
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


}
