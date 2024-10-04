<?php

namespace Drupal\ffw_dashboard_auth\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 *  Form config authentication.
 */
class PocApiSettingForm extends ConfigFormBase {

  /**
   * @inheritDoc
   */
  protected function getEditableConfigNames() {
    return ['ffw_dashboard_auth.api_settings'];
  }

  /**
   * @inheritDoc
   */
  public function getFormId() {
    return 'ffw_dashboard_api_settings';
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('ffw_dashboard_auth.api_settings');

    $form['poc_headless'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('POC headless settings'),
    ];

    $form['poc_headless']['frontend_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Frontend domain'),
      '#required' => TRUE,
      '#description' => $this->t('Frontend domain'),
      '#default_value' => $config ? $config->get('frontend_domain') : '',
    ];

    $form['poc_headless']['reset_pass_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reset password path'),
      '#required' => TRUE,
      '#description' => $this->t('Reset password path'),
      '#default_value' => $config ? $config->get('reset_pass_path') : '',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return void
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ffw_dashboard_auth.api_settings');
    $config->set('frontend_domain', $form_state->getValue('frontend_domain'))
      ->set('reset_pass_path', $form_state->getValue('reset_pass_path'));
    $config->save();

    parent::submitForm($form, $form_state);
  }
}
