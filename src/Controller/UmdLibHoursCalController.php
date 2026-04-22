<?php
/**
 * @file
 * Definition of Drupal\lib_hours\Controller\UmdLibHoursController
 */

namespace Drupal\lib_hours\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\lib_hours\Helper\UmdLibHoursSettingsHelper;
use Drupal\lib_hours\Helper\UmdLibHoursApiHelper;
use Drupal\Core\Security\TrustedCallbackInterface;
 /**
  * Implementation of UmdLibHoursController
  */
  class UmdLibHoursCalController {

    private $cid;
    private $configHelper;

    public function __construct() {
      $this->configHelper = UmdLibHoursSettingsHelper::getInstance();
      $this->cid = 'lib_hours:' . \Drupal::languageManager()
        ->getCurrentLanguage()
        ->getId();
    }

    public function getEvents($limit=3) {
      $events = NULL;
      $cached_limit = $this->getCachedEventsCount();
      if ($cached_limit >= $limit && $cache = \Drupal::cache()->get($this->cid)) {
        $events = $cache->data;
      }
      else {
        $req_limit = $cached_limit > $limit ? $cached_limit : $limit;
        $events = $this->getEventsFromApi($req_limit);
        if ($events) {
          \Drupal::cache()->set($this->cid . '_count', $req_limit);
          \Drupal::cache()->set($this->cid, $events, time() + 360);
        } else {
          return FALSE;
        }
      }
      return array_slice($events, 0, $limit);
    }

    private function getCachedEventsCount() {
      if ($cache = \Drupal::cache()->get($this->cid . '_count')) {
        return $cache->data;
      }
      return 0;
    }
  
    public function updateEquipmentDataCache($limit=3) {
      $cached_limit = $this->getCachedEventsCount();
      if ($cached_limit > $limit) {
        $limit = $cached_limit;
      }
      $events = $this->getEventsFromApi($limit);
      if ($events) {
        \Drupal::cache()->set($this->cid . '_count', $limit);
        \Drupal::cache()->set($this->cid, $events, time() + 360);
      }
    }
  
    
    public function getEventsFromApi($limit=3) {
      $endpoint = $this->configHelper->getEndpoint();
      $client_id = $this->configHelper->getClientID();
      $client_secret = $this->configHelper->getClientSecret();
      $calendar_id = $this->configHelper->getCalendarID();
      
      // Verify configuration
      if ($endpoint == null) {
        \Drupal::logger('lib_hours')->notice('LibCal API Configuration missing!');
        return FALSE;
      }

      $apiHelper = UmdLibHoursApiHelper::getInstance($endpoint, $endpoint, $client_id, $client_secret);
      $events = $apiHelper->getEvents($calendar_id, $limit);
      return $events;
    }

    /**
     * {@inheritDoc}
     */
    public static function trustedCallbacks() {
      return ['getEvents'];
    }
  }
