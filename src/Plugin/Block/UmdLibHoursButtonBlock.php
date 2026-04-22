<?php
/**
 * @file
 * Definition of Drupal\umdlib_hours\Plugin\Block\UmdLibHoursButtonBlock
 */

namespace Drupal\umdlib_hours\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\umdlib_hours\Controller\LibHoursController;
use Drupal\umdlib_hours\Helper\UmdLibHoursSettingsHelper;

/**
 * Implements the UmdLibHoursBlock
 * 
 * @Block(
 *   id = "lib_button",
 *   admin_label = @Translation("Lib Button"),
 *   category = @Translation("custom"),
 * )
 */
class UmdLibHoursButtonBlock extends BlockBase {

  private $configHelper;

  /**
   * {@inheritdoc}
   */
  public function build() {
    $blockConfig = $this->getConfiguration();

    return [
      '#theme' => 'umdlib_hours_button',
      '#button_text' => $blockConfig['button_text'],
      '#button_url' => $blockConfig['button_url'],
      '#cache' => [
        'max-age' => 3600,
      ]
    ];
  }

  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();
    $this->configHelper = UmdLibHoursSettingsHelper::getInstance();

    $form['button_text'] = [
      '#type' => 'textfield',
      '#title' => t('Button Text'),
      '#default_value' =>  isset($config['button_text']) ? $config['button_text'] : null,
      '#required' => TRUE,
    ];
    $form['button_url'] = [
      '#type' => 'textfield',
      '#title' => t('Button URL'),
      '#default_value' =>  isset($config['button_url']) ? $config['button_url'] : null,
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->setConfigurationValue('button_text', $form_state->getValue('button_text'));
    $this->setConfigurationValue('button_url', $form_state->getValue('button_url'));
  }
}
