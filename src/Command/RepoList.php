<?php
declare(strict_types=1);

namespace App\Command;

use Github\Client;
use Github\ResultPager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RepoList extends Command {

  /**
   * {@inheritdoc}
   */
  protected static $defaultName = 'repo:list';

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
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setDescription('List all repositories')
      ->setHelp('Lists all of the repositories');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $paginator = new ResultPager($this->gh);
    $repos = $paginator->fetchAll($this->gh->api('repos'), 'org', [$this->org_name]);
    $names = array_column($repos, 'name');
    uasort($names, 'strcasecmp');

    foreach ($names as $name) {
      $output->writeln($name);
    }

    return Command::SUCCESS;
  }

}
