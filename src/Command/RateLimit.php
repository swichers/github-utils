<?php
declare(strict_types=1);

namespace App\Command;

use Github\Api\RateLimit\RateLimitResource;
use Github\Client;

/**
 * Trait RateLimit
 */
trait RateLimit {

  /**
   * Causes requests to wait if we hit an API limit.
   *
   * @param \Github\Client $client
   *   The GitHub client.
   * @param string $resource
   *   The resource to check rate limit status for. Defaults to core.
   */
  protected function rateLimit(Client $client, string $resource = 'core'): void {
    /** @var RateLimitResource $resource */
    $resource = $client->api('rate_limit')->getResource($resource);
    if ($resource->getRemaining() < 10) {
      $reset = $resource->getReset();
      $sleepFor = $reset - time();
      if ($sleepFor > 0) {
        sleep($sleepFor);
      }
    }
  }

}
