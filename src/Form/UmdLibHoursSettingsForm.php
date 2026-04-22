<?php
namespace Drupal\umdlib_hours\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\umdlib_hours\Helper\UmdLibHoursSettingsHelper;
use Drupal\umdlib_hours\Helper\UmdLibHoursApiHelper;
use Drupal\taxonomy\Entity\Term;

/**
 * Settings for UmdLibHours target urls.
 */
class UmdLibHoursSettingsForm extends ConfigFormBase {


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'umdlib-hours-settings-form';
  }

  /** 
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      UmdLibHoursSettingsHelper::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Load the stored values to populate forms.
    $config = $this->config(UmdLibHoursSettingsHelper::SETTINGS);

    // @see samlauth_attrib module for an example of field to array (and reverse)

    $form['umdlib_hours_settings'] = [
      '#type' => 'item',
      '#markup' => '<h3>' . t('Configuration for UmdLibHours integration.') . '</h3>',
    ];

    $form[UmdLibHoursSettingsHelper::ENDPOINT] = [
      '#type' => 'url',
      '#title' => t('Endpoint'),
      '#default_value' => $config->get(UmdLibHoursSettingsHelper::ENDPOINT),
      '#size' => 50,
      '#maxlength' => 50,
      '#required' => TRUE,
    ];

    $form[UmdLibHoursSettingsHelper::HOURS_ENDPOINT] = [
      '#type' => 'url',
      '#title' => t('Hours Endpoint'),
      '#default_value' => $config->get(UmdLibHoursSettingsHelper::HOURS_ENDPOINT),
      '#size' => 50,
      '#maxlength' => 50,
      '#required' => FALSE,
    ];

    $form[UmdLibHoursSettingsHelper::CLIENT_ID] = [
      '#type' => 'textfield',
      '#title' => t('Client ID'),
      '#default_value' => $config->get(UmdLibHoursSettingsHelper::CLIENT_ID),
      '#size' => 50,
      '#maxlength' => 50,
      '#required' => TRUE,
    ];

    $form[UmdLibHoursSettingsHelper::CLIENT_SECRET] = [
      '#type' => 'textfield',
      '#title' => t('Client Secret'),
      '#default_value' => $config->get(UmdLibHoursSettingsHelper::CLIENT_SECRET),
      '#size' => 50,
      '#maxlength' => 50,
      '#required' => TRUE,
    ];

    $form[UmdLibHoursSettingsHelper::CALENDAR_ID] = [
      '#type' => 'textfield',
      '#title' => t('Calendar ID'),
      '#default_value' => $config->get(UmdLibHoursSettingsHelper::CALENDAR_ID),
      '#size' => 50,
      '#maxlength' => 50,
      '#required' => TRUE,
    ];
    
    $form['umdlib_hours_auth_test'] = [
      '#type' => 'textarea',
      '#title' => t('UmdLibHours API Auth Test'),
      '#default_value' => $config->get('umdlib_hours_auth_test'),
      '#disabled' => TRUE,
    ];

    $form['umdlib_hours_hours_test'] = [
      '#type' => 'textarea',
      '#title' => t('UmdLibHours API Hours Test'),
      '#default_value' => $config->get('umdlib_hours_hours_test'),
      '#disabled' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $endpoint = rtrim($form_state->getValue(UmdLibHoursSettingsHelper::ENDPOINT), '/') . '/';
    $hours_endpoint = rtrim($form_state->getValue(UmdLibHoursSettingsHelper::HOURS_ENDPOINT), '/') . '/';
    $client_id = $form_state->getValue(UmdLibHoursSettingsHelper::CLIENT_ID);
    $client_secret = $form_state->getValue(UmdLibHoursSettingsHelper::CLIENT_SECRET);
    $calendar_id = $form_state->getValue(UmdLibHoursSettingsHelper::CALENDAR_ID);

    $libraries = $form_state->getValue(UmdLibHoursSettingsHelper::LIBRARIES);

    $settings = $this->configFactory->getEditable(UmdLibHoursSettingsHelper::SETTINGS);

    $settings->set(UmdLibHoursSettingsHelper::ENDPOINT, $endpoint)
      ->set(UmdLibHoursSettingsHelper::HOURS_ENDPOINT, $hours_endpoint)
      ->set(UmdLibHoursSettingsHelper::CLIENT_ID, $client_id)
      ->set(UmdLibHoursSettingsHelper::CLIENT_SECRET, $client_secret)
      ->set(UmdLibHoursSettingsHelper::CALENDAR_ID, $calendar_id)
      ->set(UmdLibHoursSettingsHelper::LIBRARIES, $libraries)
      ->save();

    $apiHelper = UmdLibHoursApiHelper::getInstance($endpoint, $endpoint, $client_id, $client_secret);
    $events = $apiHelper->getEvents($calendar_id);
    $settings->set('umdlib_hours_auth_test', var_export($events, true))
      ->save();

    $apiHelper = UmdLibHoursApiHelper::getInstance($endpoint, $hours_endpoint, $client_id, $client_secret);
    $hours = $apiHelper->getWeeksHours();
    $settings->set('umdlib_hours_hours_test', var_export($hours, true))
      ->save();

    parent::submitForm($form, $form_state);
  }
} 
