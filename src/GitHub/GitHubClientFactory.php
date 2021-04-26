<?php
declare(strict_types=1);

namespace App\GitHub;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use Github\Client;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;

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

    $adapter = new Local(__DIR__ . '/../../');
    $fs = new Filesystem($adapter);
    $pool = new FilesystemCachePool($fs);

    $client = new Client();
    $client->addCache($pool);

    $client->authenticate($apiKey, NULL, Client::AUTH_ACCESS_TOKEN);

    return $client;
  }

}
