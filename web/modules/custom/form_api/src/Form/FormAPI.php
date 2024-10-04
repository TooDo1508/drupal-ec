<?php
/**
 * @file
 * Contains \Drupal\form_api\Form\FormAPI.
 */
namespace Drupal\form_api\Form;

use Drupal\Core\Form\FormBase;

use Drupal\Core\Form\FormStateInterface;

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




class FormAPI extends FormBase {
  /**
   * {@inheritdoc}
   */

  public function getFormId(){
    return 'mymodule_settings';
  }


  public function buildForm(array $form, FormStateInterface $form_state) {

    if (!\Drupal::currentUser()->hasPermission('access secret notes')) {
      return t('You dont have permission in this page');
    }

    $form['site_name']= [
      '#type' => 'textfield',
      '#title' => $this->t('Site Name'),

    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    $node_types = ['budget', 'customer', 'contract', 'finance', 'result', 'sale'];

    // if (!in_array($node_type, $node_types)) {
    //   throw new NotFoundHttpException($this->t("Node type not found: @node_type", ['@node_type' => $node_type]));
    // }

    // dump($node_types);

    // $node_data = [];

    // $filter_params = $this->currentRequest->query->all();

    // dump($filter_params);
    $node_query = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->getQuery()
    ->condition('type', 'budget')
    ->condition('status', 1)
    ->pager()
    ->execute();

    dump($node_query);

    $nids = $node_query;
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);

    foreach ($nodes as $node) {
      $month = 'december';
      dump($node);
      dump($node->getTitle());
      dump($node->get("field_ebitda_$month")->value);
      dump($node->uid());
//      break;
    }

    // dump();


    $count_all_nids = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->getQuery()
    ->condition('type', 'budget')
    ->condition('status', 1)
    ->count()
    ->execute();

    dump($count_all_nids);













    return $form;

  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (strlen($form_state->getValue('site_name')) > 6) {
      $form_state->setErrorByName('site_name', $this->t('The sitename too long. Please enter site name again'));
    }
  }



  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::messenger()->addMessage(t('Here is your site name'));
    $this->messenger()->addStatus($this->t('Your site name is @sitename', ['@sitename' => $form_state->getValue('site_name')]));
    // \Drupal::messenger()->addMessage(t("Student Registration Done!! Registered Values are:"));
    // $config = \Drupal::config('system.site');
    // $config->set('name' , $form_state->getValue('site_name'));
    // $config->save();

    \Drupal::configFactory()->getEditable('system.site')->set('name', $form_state->getValue('site_name'))->save();
    // dump($config);
    // $variables['site']['name'] = $config->get('name');
    // $this->messenger()->addStatus(($config->get('name')));
  }


}
