<?php

namespace Drupal\elereg\Trait;

use chillerlan\QRCode\QRCode;
use Drupal;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\elereg\Controller\EleregController;
use Drupal\node\Entity\Node;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

trait RegistrationTrait
{

    /**
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    private function getServices(bool $full = false): array
    {
        $ret = [];
        $vocabulary = Drupal::entityTypeManager()->getStorage('taxonomy_term');
        /**
         * @var $vocabulary \Drupal\taxonomy\TermStorage
         */
        $terms = $vocabulary->loadTree(EleregController::VOC_SERVICES);
        /**
         * @var $term \Drupal\taxonomy\Entity\Term
         */
        foreach ($terms as $term) {
            if ($term->status || $full) {
                $ret[] = [
                    'id' => $term->tid,
                    'name' => $term->name,
                    'short' => $term->description__value,
                ];
            }
        }
        return $ret;
    }

    private function saveRegistrationNode(array $values, array &$ret, bool $generateQR = false): Node|null
    {
        /**
         * @var $date DrupalDateTime
         */
        $date = $ret['up']['date'];
        $node = Node::create(['type' => 'registration', 'title' => $ret['up']['title']]);
        $node->set('field_data', $date->format('Y-m-d\TH:i:s'))->set('field_tel', $ret['up']['tel'])->set('field_fio', $ret['up']['fio']);
        unset($ret['up']);
        $services = [];
        foreach ($values['Services'] as $serviceId) {
            $services[] = ['target_id' => $serviceId];
        }
        $path = '/' . Uuid::v4();
        $node->set('field_services', $services)->set('field_description', 'Самозапись')->set('path', ['alias' => $path, 'pathauto' => 0]);
        if ($generateQR) {
            $url = 'http' . ($_SERVER['SERVER_PORT'] == 80 ? '' : 's') . '://' . $_SERVER['HTTP_HOST'] . $path;
            $ret['qr'] = (new QRCode())->render($url);
        }
        try {
            $node->save();
        } catch (EntityStorageException|InvalidArgumentException $e) {
            $ret['error'] = 'Ошибка сохранения';
            $ret['errorMessage'] = $e->getMessage();
            $ret['status'] = 'error';
            return null;
        }
        $ret['nid'] = $node->id();

        return $node;
    }

}