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
 *   id = "process_node_by_type",
 *   label = @Translation("Process node by type"),
 *   uri_paths = {
 *     "canonical" = "/api/node/{node_type}",
 *     "create" = "/api/node/{node_type}"
 *   }
 * )
 */
class ProcessNodeByType extends ResourceBase {

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
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *  Throws exception expected.
   */
  public function get($node_type = NULL)
  {
    // Validate content type.
    $node_types = ['budget', 'customer', 'contract', 'finance', 'result', 'sale'];
    if (!in_array($node_type, $node_types)) {
      throw new NotFoundHttpException($this->t("Node type not found: @node_type", ['@node_type' => $node_type]));
    }
    $node_data = [];
    $items_per_page = 3;

    // Retrieve filter params in URL.
    $filter_params = $this->currentRequest->query->all();

    $node_query = $this->entityTypeManager->getStorage('node')->getQuery()->condition('type', $node_type)->condition('status', 1)->pager($items_per_page);
    $count_all_nids = $this->entityTypeManager->getStorage('node')->getQuery()->condition('type', $node_type)->condition('status', 1)->count()->execute();
    if (isset($filter_params['year'])) {
      $year_filter = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
        'name' => $filter_params['year'],
        'vid' => 'years'
      ]);
      if (!empty($year_filter)) {
        $year_tid = current($year_filter)->id();
        $node_query->condition('field_year', $year_tid);
      }
    }
    // Validate pager input.
    $valid_pager = is_numeric($filter_params['page'][0]) && gettype($filter_params['page'][0] + 0) == 'integer';


    $pagination = [
      'current_page' => 0,
      'total_page' => $count_all_nids / $items_per_page,
      'offset' => $items_per_page,
    ];
    if (isset($filter_params['page']) && $valid_pager) {
      $pagination['current_page'] = (int) $filter_params['page'][0];
    }

    $nids = $node_query->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    foreach ($nodes as $node) {
      $data = [
        'nid' => $node->id(),
        'title' => $node->label(),
        'node_type' => $node_type,
      ];
      $year_ref = $this->entityTypeManager->getStorage('taxonomy_term')->load($node->field_year->target_id);
      $data['year'] = [
        'id' => $year_ref->id(),
        'name' => $year_ref->name->value,
      ];
      switch ($node_type) {
        case 'budget' || 'result':
          foreach (FFWDashboardAPI::MONTH_NAMES as $month) {
            $revenue[$month] = $node->get("field_revenue_$month")->value ? $node->get("field_revenue_$month")->value : "";
            $ebitda[$month] = $node->get("field_ebitda_$month")->value ? $node->get("field_ebitda_$month")->value : "";
          }
          break;
        case 'customer' || 'contract' || 'sale':
          $revenue = $node->get("field_revenue")->value ? $node->get("field_revenue")->value : "";
          $region_ref =
            $this->entityTypeManager->getStorage('taxonomy_term')->load($node->field_region->target_id);
          $data['region'] = [
            'id' => $region_ref->id(),
            'name' => $region_ref->name->value,
          ];
          if ($node->hasField('field_customer') && !$node->get('field_customer')->isEmpty()) {
            $customer_ref =
              $this->entityTypeManager->getStorage('node')->load($node->field_customer->target_id);
            $data['customer'] = [
              'id' => $customer_ref->id(),
              'name' => $customer_ref->label(),
            ];
          }
          if ($node->hasField('field_quarter') && !$node->get('field_quarter')->isEmpty()) {
            $quarter_ref =
              $this->entityTypeManager->getStorage('taxonomy_term')->load($node->field_quarter->target_id);
            $data['quarter'] = [
              'id' => $quarter_ref->id(),
              'name' => $quarter_ref->name->value,
            ];
          }
          break;
        default:
          break;
      }
      if (isset($ebitda)) {
        $data['ebitda'] = $ebitda;
      }
      if (isset($revenue)) {
        $data['revenue'] = $revenue;
      }
      $node_data[] = $data;
    }
    $node_data['pagination'] = $pagination;

    $response = new ResourceResponse($node_data);
    $response->addCacheableDependency($node_data);
    return $response;
  }

  /**
   * Responds to POST requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function post($data, $node_type) {
    // Check if data passed is valid with API endpoint.
    if ($data['node_type'] != $node_type) {
      throw new HttpException(403, 'Invalid node type data: ' . $data['node_type']);
    }
    // Check if new Budget/Result node has field_year value matches one among existing nodes.
    $invalid_node = $this->entityTypeManager->getStorage('node')->getQuery()->condition('type', $data['node_type'])->condition('status', 1)->condition('field_year', $data['year']['id'])->execute();
    if (!empty($invalid_node)) {
      throw new HttpException(403, 'There is already a node exists for ' . $data['year']['name']);
    }
    // If it passes, prepares data to create node.
    $node_data = [
      'type' => $data['node_type'],
      'title' => $data['title'],
      'field_year' => [
        ['target_id' => $data['year']['id']],
      ],
    ];
    foreach (FFWDashboardAPI::MONTH_NAMES as $month) {
      if (isset($data['ebitda'][$month])) {
        $node_data["field_ebitda_$month"] = $data['ebitda'][$month];
      }
      if (isset($data['revenue'][$month])) {
        $node_data["field_revenue_$month"] = $data['revenue'][$month];
      }
    }
    $node = Node::create($node_data);
    
    // Validate the received data before saving.
    $this->validate($node);
    try {
      $node->save();
      $this->logger->notice('Created node %title of type %type with ID %id.', ['%title' => $node->label(), '%type' => $node->getType(), '%id' => $node->id()]);

      // 201 Created responses return the newly created entity in the response
      // body. These responses are not cacheable, so we add no cacheability
      // metadata here.
      $headers = [];
      if (in_array('canonical', $node->uriRelationships(), TRUE)) {
        $url = $node->toUrl('canonical', ['absolute' => TRUE])->toString(TRUE);
        $headers['Location'] = $url->getGeneratedUrl();
      }
      return new ModifiedResourceResponse($node, 201, $headers);
    } catch (EntityStorageException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
  }
}
