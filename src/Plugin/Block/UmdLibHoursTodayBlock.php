<?php
/**
 * @file
 * Definition of Drupal\umdlib_hours\Plugin\Block\UmdLibHoursTodayBlock
 */

namespace Drupal\umdlib_hours\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\umdlib_hours\Controller\UmdLibHoursController;
use Drupal\umdlib_hours\Helper\UmdLibHoursSettingsHelper;
use Drupal\Core\Routing;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Url;

/**
 * Implements the UmdLibHoursBlock
 * 
 * @Block(
 *   id = "lib_hours_today",
 *   admin_label = @Translation("UmdLib Hours"),
 *   category = @Translation("custom"),
 * )
 */
class UmdLibHoursTodayBlock extends BlockBase {

  private $configHelper;

  /**
   * {@inheritdoc}
   */
  public function build() {
    $debug_date = \Drupal::request()->query->get('debug_date');
    $blockConfig = $this->getConfiguration();
    $libHoursController = new UmdLibHoursController();
    $is_mobile = false;
    $librariesInfo = $this->getLibrariesLocations();
    if (empty($librariesInfo)) {
      return;
    }

    $urls = $librariesInfo['urls'];
    $locations = $librariesInfo['locations'];
    $week_date = null;

    if ($blockConfig['weekly_display']) {
      $template = 'lib_hours_range';
      $hours = $libHoursController->getThisWeek($blockConfig['libraries'], $debug_date);
    } else {
      switch ($blockConfig['display_type']) {
        case 'today':
          $template = 'umdlib_hours_today';
          $hours = $libHoursController->getToday($blockConfig['libraries'], $debug_date);
          $hours = $this->sortLocations($hours);
          $hours = $this->sortLocationsHeirarchy($hours, $locations);
          break;
        case 'weekly':
          $template = 'umdlib_hours_range';
          $hours = $libHoursController->getThisWeek($blockConfig['libraries'], $debug_date);
          $week_date = $hours['hours_from'];
          unset($hours['hours_from']);
          $hours = $this->sortLocations($hours);
          break;
        case 'utility_nav':
          $template = 'umdlib_hours_today_util';
          $hours = $libHoursController->getToday($blockConfig['libraries'], $debug_date);
          unset($hours['hours_from']);
          $hours = $this->sortLocations($hours);
          $is_mobile = $blockConfig['is_mobile'];
          break;
         default:
          $template = 'umdlib_hours_today';
          break;
      }
    }

    $row_class = 'lib-hours-constrained';
    $grid_class = null;
    $current_date = null;
    if ($blockConfig['date_display']) {
      if ($debug_date != null) {
        $current_date = $debug_date;
      } else {
        $current_date = date("c");
      }
    }

    $hours_class = 'hours-main-grid';
    if (count($hours) == 1) {
      $hours_class = 'hours-main';
    }

    return [
      '#theme' => $template,
      '#locations' => $locations,
      '#urls' => $urls,
      '#hours' => $hours,
      '#row_class' => $row_class,
      '#grid_class' => $grid_class,
      '#hours_class' => $hours_class,
      '#current_date' => $current_date,
      '#week_date' => $week_date,
      '#is_mobile' => $is_mobile,
      '#shady_grove_url' => $blockConfig['shady_grove_url'],
      '#all_libraries_url' => $blockConfig['all_libraries_url'],
      '#cache' => [
        'max-age' => 3600,
      ]
    ];
  }

  function sortLocations($hours) {
    $output = [];
    $mckeldin = null;

    $sortable = [];
    foreach ($hours as $key => $loc) {
      if (!empty($loc['name'])) {
        $name = strtolower($loc['name']);
        if ($name != "mckeldin library") {
          $sortable[$name] = $loc;
        } else {
          $mckeldin = $loc;
        }
      }
    }
    ksort($sortable);
    if ($mckeldin != null) {
      $output[] = $mckeldin;
    }
    foreach ($sortable as $key => $sorted) {
      if (!empty($sorted['name'])) {
        $output[] = $sorted;
      }
    }
    return $output;
  }

  function sortLocationsHeirarchy($hours, &$locations) {
    $children = [];
    $mckeldin = null;
    foreach ($hours as $key => $loc) {
      if (!empty($loc['parent_lid'])) {
        $lid = $loc['lid'];
        $loc['name'] = '|chev| ' . $loc['name'];
        if (!empty($locations[$lid])) {
          $t_title = '|chev| ' . $locations[$lid];
          $locations[$lid] = $t_title;
        }
        $children[$loc['parent_lid']][] = $loc;
        unset($hours[$key]);
      }
    }
    $output = [];
    // Check parents for children
    foreach ($hours as $loc) {
      $plid = $loc['lid'];
      $output[] = $loc;
      if (!empty($children[$plid])) {
        foreach ($children[$plid] as $child) {
          $output[] = $child;
        }
        unset($children[$plid]);
      }
    }
    // If there are orphans, add those but on top level.
    if (!empty($children)) {
      foreach ($children as $key => $orphans) {
        foreach ($orphans as $orphan) {
          // We no longer want chevs for these
          if ($olid = $orphan['lid']) {
            $orphan['name'] = str_replace('|chev| ', '', $orphan['name']);
            if (!empty($locations[$olid])) {
              $locations[$olid] = str_replace('|chev| ', '', $locations[$olid]);
            }
            $output[] = $orphan;
          }
        }
      }
    }
    return $output;
  }

  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();
    $this->configHelper = UmdLibHoursSettingsHelper::getInstance();
    $librariesInfo = $this->getLibrariesLocations();

    $form['libraries'] = [
      '#type' => 'select',
      '#title' => t('Libraries'),
      '#default_value' =>  !empty($config['libraries']) && is_string($config['libraries']) ? explode(',',$config['libraries']) : array(),
      '#required' => TRUE,
      '#options' => !empty($librariesInfo) ? $librariesInfo['locations'] : [],
      '#multiple' => TRUE,
    ];
    $form['all_libraries_url'] = [
      '#type' => 'textfield',
      '#title' => t('All Libraries URL'),
      '#default_value' =>  !empty($config['all_libraries_url']) ? $config['all_libraries_url'] : null,
    ];
    $form['shady_grove_url'] = [
      '#type' => 'textfield',
      '#title' => t('Shady Grove Hours URL'),
      '#default_value' =>  !empty($config['shady_grove_url']) ? $config['shady_grove_url'] : null,
    ];
    $display_types = ['today' => t('Today'), 'weekly' => t('Weekly'), 'utility_nav' => t('Utility Nav')];
    $form['display_type'] = [
      '#type' => 'select',
      '#title' => t('Display Type'),
      '#default_value' => !empty($config['display_type']) ? $config['display_type'] : null,
      '#required' => TRUE,
      '#options' => $display_types,
    ];
    $form['is_mobile'] = [
      '#type' => 'checkbox',
      '#title' => t('Is Mobile Block?'),
      '#description' => t('Note: Only affects Utility Nav displays. This option is otherwise ignored.'),
      '#default_value' => !empty($config['is_mobile']) ? $config['is_mobile'] : NULL,
    ];
    $form['date_display'] = [
      '#type' => 'checkbox',
      '#title' => t('Show current/weekly date?'),
      '#default_value' => !empty($config['date_display']) ? $config['date_display'] : NULL,
    ];
    return $form;
  }

  public function getLibrariesLocations() {
    $vid = 'library_locations';
    $terms =\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid);
    $output = [];
    $locations_data = [];
    $url_data = [];
    foreach ($terms as $t) {
      $term = Term::load($t->tid);
      if (!empty($term->get('field_libcal_location_id'))) {
        $libcal = $term->get('field_libcal_location_id')->value;
        if ($libcal != null) {
          $locations_data[$libcal] = $term->getName();
          if (!empty($term->field_location_details_page) && !empty($term->field_location_details_page->first())) {
            $liburl = Url::fromUri($term->field_location_details_page->first()->uri);
            if ($liburl != null) {
              $url_data[$libcal] = $liburl->toString();
            } 
          }
        }
      }
    }
    if (count($locations_data) > 0) {
      $output['locations'] = $locations_data;
    }
    if (count($url_data) > 0) {
      $output['urls'] = $url_data;
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $libraries = $form_state->getValue('libraries');
    // We have to have at least one library selected.
    if (empty($libraries) || (is_array($libraries) && count($libraries) == 0)) {
      return;
    }

    // the api wants a comma-seperated string.
    $libraries = implode(',', $libraries);
    $this->setConfigurationValue('libraries', $libraries);
    $this->setConfigurationValue('shady_grove_url', $form_state->getValue('shady_grove_url'));
    $this->setConfigurationValue('all_libraries_url', $form_state->getValue('all_libraries_url'));
    $this->setConfigurationValue('branch_suffix', $form_state->getValue('branch_suffix'));
    $this->setConfigurationValue('weekly_display', $form_state->getValue('weekly_display'));
    $this->setConfigurationValue('grid_display', $form_state->getValue('grid_display'));
    $this->setConfigurationValue('date_display', $form_state->getValue('date_display'));
    $this->setConfigurationValue('display_type', $form_state->getValue('display_type'));
    $this->setConfigurationValue('is_mobile', $form_state->getValue('is_mobile'));
  }
}
