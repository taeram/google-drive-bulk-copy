<?php

namespace Taeram;

class Google {

  /**
   * The Google Client
   *
   * @var \Google_Client
   */
  protected $client;

  /**
   * The logger
   *
   * @var \Monolog\Logger
   */
  protected $logger;

  /**
   * Set the credentials
   *
   * @param string $applicationCredentialsJson The path to the application
   *   credentials JSON file.
   * @param string $delegatedUser The user to impersonate
   *
   * @throws \Exception
   */
  public function __construct($applicationCredentialsJson, $delegatedUser = NULL) {
    if (!file_exists($applicationCredentialsJson)) {
      throw new \Exception("Cannot find service account JSON file");
    }

    $this->client = new \Google_Client();
    $this->client->setAuthConfig($applicationCredentialsJson);
    if ($delegatedUser) {
      $this->client->setSubject($delegatedUser);
    }
  }

  /**
   * Set the logger
   *
   * @param \Monolog\Logger $logger
   */
  public function setLogger(\Monolog\Logger $logger) {
    $this->logger = $logger;
  }

  /**
   * Call a Google Function
   *
   * @param object $service The Google Service
   * @param string $functionName The function name
   * @param array $args The function arguments
   * @param integer $requestNum The request number. Used only in recursion.
   *
   * @return
   */
  public function call($service, $functionName, $args, $requestNum = 0) {
    try {
      if (count($args) == 1) {
        return $service->$functionName($args[0]);
      }
      elseif (count($args) == 2) {
        return $service->$functionName($args[0], $args[1]);
      }
      elseif (count($args) == 3) {
        return $service->$functionName($args[0], $args[1], $args[2]);
      }
    } catch (\Exception $e) {
      if ($e->getCode() == 403) {

        // Exponentially increase the wait time
        $requestNum++;
        $sleepSeconds = (pow($requestNum, 2) + mt_rand(0, 1));

        // Wait for a number of seconds before retrying
        $errors = json_decode($e->getMessage(), true);
        if (isset($errors['error']['errors'][0]['message'])) {
          echo " - " . $errors['error']['errors'][0]['message'] . "\n";
        }
        die();

        usleep($sleepSeconds * 1000000);

        return $this->call($service, $functionName, $args, $requestNum);
      }
      elseif ($e->getCode() == 500) {
        // Exponentially increase the wait time
        $requestNum++;
        $sleepSeconds = (pow($requestNum, 2) + mt_rand(0, 1));

        // Wait for a number of seconds before retrying
        echo "\033[1;31m" . "E" . "\033[0m";
        usleep($sleepSeconds * 1000000);

        return $this->call($service, $functionName, $args, $requestNum);
      }
      else {
        throw new \Exception ($e->getMessage(), $e->getCode(), $e);
      }
    }
  }
}
