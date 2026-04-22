<?php

/**
 * @file
 * Definition of Drupal\umdlib_hours\Helper\UmdLibHoursApiHelper
 */

namespace Drupal\umdlib_hours\Helper;

use Drupal\Component\Datetime\DateTimePlus;
use DateInterval;

/**
 * Helper class for interacting with UmdLibHours API
 */
class UmdLibHoursApiHelper {

  private $auth_endpoint;
  private $data_endpoint;
  private $client_id;
  private $client_secret;
  private $token;
  private $token_expiry;

  static $instance;

  public static function getInstance($auth_endpoint, $data_endpoint, $client_id, $client_secret)
  {
    if (is_null( self::$instance) )
    {
      self::$instance = new self();
    }
    self::$instance->auth_endpoint = $auth_endpoint;
    self::$instance->data_endpoint = $data_endpoint;
    self::$instance->client_id = $client_id;
    self::$instance->client_secret = $client_secret;
    return self::$instance;
  }

  private function isTokenValid() {
    if ($this->token != null && $this->token_expiry > time()) {
      return TRUE;
    }
    return FALSE;
  }

  private function getTokenString() {
    if (!$this->isTokenValid()) {
      $curr_time = time();
      $token_array = $this->requestToken();
      if ($token_array == null) {
        \Drupal::logger('umdlib_hours')->notice('UmdLibHours API Token request failed!');
        return null;
      } else {
        $this->token = $token_array['access_token'];
        $this->token_expiry = $curr_time + $token_array['expires_in'];
      }
    }
    return $this->token;
  }

  public function requestToken() {
    $token_url = $this->auth_endpoint . 'oauth/token';
    $params = [
      'client_id' => $this->client_id,
      'client_secret' => $this->client_secret,
      'grant_type' => 'client_credentials',
    ];
    return $this->curlRequest($token_url, false, $params);
  }

  public function getWeeksHours($date = null, $libraries = '13231,17166,17167,17168,17964,17965,17966') {
    if (empty($date)) {
      $date = date("Y-m-d");
    }
    $dateTime = new DateTimePlus($date);
    $weekNo = $this->getWeekOfYear($dateTime);
    $weekDebug = $dateTime->format("W");
    $week = new DateTimePlus();
    $week->setISODate($dateTime->format("Y"), $weekNo, 0);
    // $from_date = $week->modify("-1 days")->format('Y-m-d');
    $from_date = $week->format('Y-m-d');
    // $to_date = $week->modify("+6 days")->format('Y-m-d');
    $to_date = $week->modify("+6 days")->format('Y-m-d');
    return $this->getHours($from_date, $to_date, $libraries);
  }

  function getWeekOfYear(DateTimePlus $date) {
    $dayOfWeek = intval($date->format('w'));
    if ($dayOfWeek == 0) {
      $date->add(new DateInterval('P1D'));
    }
    return intval($date->format('W'));
  }

  public function getHours($from = null, $to = null, $libraries = '13231,17166,17167,17168,17964,17965,17966') {
    $raw_from = $from;
    if ($from = $this->validateDate($from)) {
      $raw_from = $from;
      $from = '&from=' . $from;
    }
    if ($to = $this->validateDate($to)) {
      $to = '&to=' . $to;
    }
    $hours_url = $this->data_endpoint . $libraries . '?' . $from . $to;
    $response = $this->curlRequest($hours_url, $this->getTokenString());
    $response['hours_from'] = $raw_from;
    return $response;
  }

  function validateDate($date) {
    if ($date != null) {
      $format = 'Y-m-d';
      $date = date_create_from_format($format, $date);
      if ($date) {
        return $date->format($format);
      }
    }
    return null;
  }

  public function getEvents($calendar_id, $limit=3, $days_out=90) {
    $events_url = $this->data_endpoint . "events?cal_id=$calendar_id&limit=$limit&days=$days_out";
    $response = $this->curlRequest($events_url, $this->getTokenString());
    $processed_events = null;
    if ($response != null) {
      $events = $response['events'];
      $processed_events = array_map(function ($event) {
        $date = date_create_from_format('Y-m-d\TH:i:sT', $event['start']);
        return [
          'id' => $event['id'],
          'title' => $event['title'],
          'month' => date_format($date, 'M'),
          'day' => date_format($date, 'j'),
          'hour' => date_format($date, 'g:iA'),
          'url' => $event['url']['public'],
          'category' => array_values(
            array_filter(
              array_map(
                function ($category) {
                  if (!str_contains($category['name'], '>')) return $category['name']; 
                },
                $event['category']
              )
            )
          )
        ];
      }, $events);
    }
    return $processed_events;
  }

  private function arrayToParams($arr) {
    return join('&', array_map(function ($key, $val) { return "$key=$val";}, array_keys($arr), $arr));
  }
  
  private function curlRequest($url, $bearer_token, $post_fields = false) {
    $curl = curl_init();
    if ($bearer_token) {
      curl_setopt($curl, CURLOPT_HTTPHEADER, ["Authorization: Bearer $bearer_token"]);
    }
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    if ($post_fields && count($post_fields) > 0) {
      curl_setopt($curl, CURLOPT_POST, 1);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $this->arrayToParams($post_fields));
    }
    $output = curl_exec($curl);
    if (curl_errno($curl)) {
      \Drupal::logger('umdlib_hours')->notice("The curl request to $url failed "
      . curl_errno($curl) . '. ' . curl_error($curl));
    } elseif (curl_getinfo($curl, CURLINFO_HTTP_CODE) != 200) {
      \Drupal::logger('umdlib_hours')->notice("The upstream request $url failed with HTTP status code: "
      . curl_getinfo($curl, CURLINFO_HTTP_CODE));
    } else {
      $data = json_decode($output, true);
      return $data;
    }
    return null;
  }
} 
