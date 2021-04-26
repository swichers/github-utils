<?php
declare(strict_types=1);

namespace App\GitHub;

use Github\Client;

/**
 * Class GitHubClientFactory.
 *
 * Service factory for creating a GitHub client using Drupal configuration.
 */
class GitHubClientFactory {

  /**
   * Get a GitHub client.
   *
   * @return \Github\Client
   *   An authenticated GitHub client.
   */
  public static function get(string $apiKey): Client {

    $client = new Client();

    $client->authenticate($apiKey, NULL, Client::AUTH_ACCESS_TOKEN);

    return $client;
  }

}
