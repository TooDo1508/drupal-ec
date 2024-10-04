<?php

namespace Drupal\ffw_dashboard_api\Plugin\rest\resource;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\rest\resource\EntityResourceValidationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\ffw_dashboard_api\FFWDashboardAPI;

/**
 * Provides a resource to get all or create a node by bundle.
 *
 * @RestResource(
 *   id = "process_node_test",
 *   label = @Translation("Process node test"),
 *   uri_paths = {
 *     "canonical" = "/api/nodetest/{node_type}",
 *   }
 * )
 */
class ProcessNodeTest extends ResourceBase {
    use EntityResourceValidationTrait;

  /**
   * A current user instance which is logged in the session.
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $loggedUser;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected EntityTypeManager $entityTypeManager;

  /**
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $currentRequest;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $config
   *   A configuration array which contains the information about the plugin instance.
   * @param string $module_id
   *   The module_id for the plugin instance.
   * @param mixed $module_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A currently logged user instance.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   * @param \Symfony\Component\HttpFoundation\Request $currentRequest
   *   The current request
   */
  public function __construct(array $config, $module_id, $module_definition, array $serializer_formats, LoggerInterface $logger, AccountProxyInterface $current_user, EntityTypeManager $entityTypeManager, Request $currentRequest) {
    parent::__construct($config, $module_id, $module_definition, $serializer_formats, $logger, $entityTypeManager, $currentRequest);
    $this->logger = $logger;
    $this->loggedUser = $current_user;
    $this->entityTypeManager = $entityTypeManager;
    $this->currentRequest = $currentRequest;
  }
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $config, $module_id, $module_definition)
  {
    return new static(
      $config,
      $module_id,
      $module_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('ffw_dashboard_api'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }
    /**
        * Responds to GET request.
        *
        * @return \Drupal\rest\ResourceResponse
        *  The HTTP response object.
        *

    */
    public function get(){

//      $node_types = ['budget', 'customer', 'contract', 'finance', 'result', 'sale'];
//      $node_types = ['budget'];
      $node_types = ['result'];
//         if (!in_array($node_type, $node_types)) {
//             throw new NotFoundHttpException($this->t("Node type not found: @node_type", ['@node_type' => $node_type]));
//         }
      $node_data = [];
      $filter_params = $this->currentRequest->query->all();
      $year = $filter_params['year'];
      $node_query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->condition('type', $node_types)
      ->condition('status', 1);
//        ->pager($items_per_page);
      $nids = $node_query->execute();
      $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

      foreach ($nodes as $node) {
        $data = [
          'nid' => $node->id(),
          'title' => $node->label(),
        ];

        $year = $this->entityTypeManager->getStorage('taxonomy_term')->load($node->field_year->target_id);

        $data['year'] = [
          'tid' => $year->id(),
          'name' => $year->getName(),
        ];

        foreach (FFWDashboardAPI::MONTH_NAMES as $month) {
          $ebitda[$month] = $node->get("field_ebitda_$month")->value;
          $revenue[$month] = $node->get("field_revenue_$month")->value;
        };

        $data['ebitda'] = $ebitda;
        $data['revenue'] = $revenue;
        $node_data[] = $data;
      }

      $a ="2";
      $b=0;

      $check_data = $a ? $a :"5";

      $response = new ResourceResponse($node_data);
      $response->addCacheableDependency($node_types);

//       $response = $node_types;
      return $response;

    }


}
