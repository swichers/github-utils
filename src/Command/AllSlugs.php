<?php
declare(strict_types=1);

namespace App\Command;

use Github\Client;
use Github\ResultPager;

/**
 * Trait AllSlugs
 */
trait AllSlugs {

  /**
   * @param \Github\Client $client
   *   A GitHub client.
   * @param string $resource
   *   The GitHub resource endpoint.
   * @param string $method
   *   The class method to call. Varies by resource.
   * @param string $extractKey
   *   The key to extract from the result data.
   * @param array|string[] $params
   *   Params to pass to the resource method. Varies by resource.
   *
   * @return array
   *   The result data.
   */
  protected function getAllSlugs(Client $client, string $resource, string $method = 'all', string $extractKey = 'slug', array $params = ['example-org']): array {
    $paginator = new ResultPager($client);
    $result = $paginator->fetchAll($client->api($resource), $method, $params);

    return array_column($result, $extractKey);
  }

}
