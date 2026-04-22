<?php
/**
 * @file
 * Definition of Drupal\umdlib_hours\Controller\LibHoursController
 */

namespace Drupal\umdlib_hours\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\umdlib_hours\Helper\UmdLibHoursSettingsHelper;
use Drupal\umdlib_hours\Helper\UmdLibHoursApiHelper;
use Drupal\Core\Security\TrustedCallbackInterface;
 /**
  * Implementation of LibHoursController
  */
  class UmdLibHoursController {

    private $cid;
    private $configHelper;

    public function __construct() {
      $this->configHelper = UmdLibHoursSettingsHelper::getInstance();
    }

    public function getThisWeek($libraries, $hours = null) {
      return $this->getWeekFromAPI($libraries, $hours);
    }

    public function getToday($libraries, $hours = null) {
      return $this->getTodayFromAPI($libraries, $hours);
    }

    private function getCachedEventsCount() {
      if ($cache = \Drupal::cache()->get($this->cid . '_count')) {
        return $cache->data;
      }
      return 0;
    }
  
    public function getWeekFromApi($libraries, $date = null) {
      $auth_endpoint = $this->configHelper->getEndpoint();
      $data_endpoint = $this->configHelper->getHoursEndpoint();
      $client_id = $this->configHelper->getClientID();
      $client_secret = $this->configHelper->getClientSecret();
      
      // Verify configuration
      if ($auth_endpoint == null) {
        \Drupal::logger('umdlib_hours')->notice('UmdLibHours API Configuration missing!');
        return FALSE;
      }

      $apiHelper = UmdLibHoursApiHelper::getInstance($auth_endpoint, $data_endpoint, $client_id, $client_secret);
      $hours = $apiHelper->getWeeksHours($date, $libraries);
      return $hours;
    }

    
    public function getTodayFromApi($libraries, $date = null) {
      $auth_endpoint = $this->configHelper->getEndpoint();
      $data_endpoint = $this->configHelper->getHoursEndpoint();
      $client_id = $this->configHelper->getClientID();
      $client_secret = $this->configHelper->getClientSecret();
      
      // Verify configuration
      if ($auth_endpoint == null) {
        \Drupal::logger('umdlib_hours')->notice('LibCal API Configuration missing!');
        return FALSE;
      }

      $apiHelper = UmdLibHoursApiHelper::getInstance($auth_endpoint, $data_endpoint, $client_id, $client_secret);
      $hours = $apiHelper->getHours($date, $date, $libraries);
      return $hours;
    }

    /**
     * {@inheritDoc}
     */
    public static function trustedCallbacks() {
      return ['getToday'];
    }
  }
