<?php
declare(strict_types=1);

namespace App\Command;

use Github\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AbstractCommand
 */
abstract class AbstractCommand extends Command {

  use RateLimit;
  use AllSlugs;

  /**
   * A GitHub client instance.
   *
   * @var \Github\Client
   */
  protected $gh;

  /**
   * The GitHub organization name.
   *
   * @var string
   */
  protected $org_name;

  /**
   * AbstractReportCommand constructor.
   *
   * @param \Github\Client $client
   *  A ready-to-go GitHub client.
   * @param string $organizationName
   *   The GitHub organization to run reports against.
   * @param string|null $name
   *   The command name.
   */
  public function __construct(Client $client, string $organizationName, string $name = NULL) {
    parent::__construct($name);

    $this->gh = $client;
    $this->org_name = $organizationName;
  }

  /**
   * Print report data as a table.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The console output interface.
   * @param array $headers
   *   The headers of the table.
   * @param array $data
   *   The data for the report table.
   */
  protected function tableOutput(OutputInterface $output, array $headers, array $data): void {
    // If we weren't given nested tables assume it was single column data and
    // wrap each element in their own array for display in the table.
    if (!empty($data) && (($first = current($data)) && !is_array($first))) {
      $data = array_map(static function ($element) {
        return [$element];
      }, $data);
    }

    $table = new Table($output);
    $table
      ->setHeaders($headers)
      ->setRows($data);
    $table->render();
  }

}
