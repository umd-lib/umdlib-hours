<?php

/**
 * @file
 * Definition of Drupal\umdlib_hours\Helper\UmdLibHoursSettingsHelper
 */

namespace Drupal\umdlib_hours\Helper;

/**
 * Helper class for retrieving search target settings
 */
class UmdLibHoursSettingsHelper {

  // Constants
  const SETTINGS = 'umdlib_hours.settings';
  const ENDPOINT = 'umdlib_hours_endpoint';
  const HOURS_ENDPOINT = 'lib_hours_endpoint';
  const CLIENT_ID = 'umdlib_hours_client_id';
  const CLIENT_SECRET = 'umdlib_hours_client_secret';
  const CALENDAR_ID = 'umdlib_hours_calendar_id';
  const LIBRARIES = 'lib_hours_libraries';

  protected $config;

  private static $instance;

  private function __construct() {
    $this->config = \Drupal::config(static::SETTINGS);
  }

  public static function getInstance()
  {
    if ( is_null( self::$instance ) )
    {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function getEndpoint() {
    return $this->config->get(static::ENDPOINT);
  }

  public function getHoursEndpoint() {
    return $this->config->get(static::HOURS_ENDPOINT);
  }

  public function getClientID() {
    return $this->config->get(static::CLIENT_ID);
  }

  public function getClientSecret() {
    return $this->config->get(static::CLIENT_SECRET);
  }

  public function getCalendarID() {
    return $this->config->get(static::CALENDAR_ID);
  }

  public function getLibraries() {
    return $this->config->get(static::LIBRARIES);
  }

  public function getLibrariesOptions() {
    $libs = $this->config->get(static::LIBRARIES);

    $values = [];

    $list = explode("\n", $libs);
    $list = array_map('trim', $list);

    foreach ($list as $position => $text) {
      $matches = [];
      if (preg_match('/(.*)\|(.*)/', $text, $matches)) {
        // Trim key and value to avoid unwanted spaces issues.
        $key = strtolower(trim($matches[1]));
        $value = trim($matches[2]);
      }
      $values[$key] = $value;
    }
    return $values;
  }
} 
