<?php
declare(strict_types=1);

namespace App;

use Symfony\Component\Console\Application as BaseApplication;

/**
 * Class Application
 */
class Application extends BaseApplication {

  /**
   * Constructor.
   */
  public function __construct(iterable $commands, string $version) {
    parent::__construct('GitHub Utilities', $version);

    foreach ($commands as $command) {
      $this->add($command);
    }
  }

}
