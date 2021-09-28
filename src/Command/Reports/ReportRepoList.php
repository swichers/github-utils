<?php

declare(strict_types=1);

namespace App\Command\Reports;

use Github\ResultPager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ReportRepoList
 */
class ReportRepoList extends AbstractReportCommand {

  /**
   * {@inheritdoc}
   */
  protected static $defaultName = 'repos:report:list';

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setDescription('List all repositories')
      ->setHelp('Lists all of the repositories')
      ->addOption('names-only', NULL, InputOption::VALUE_NONE, 'Only output the repository names');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {

    $names_only = $input->getOption('names-only');

    if (empty($names_only)) {
      $output->writeLn('<info>Listing repositories.</info>');
    }

    $repos = $this->getReportData($output);
    if (!empty($repos) && empty($names_only)) {
      $output->writeln('');
      $this->tableOutput($output, ['Repository'], $repos);
    }
    elseif (!empty($repos) && !empty($names_only)) {
      foreach ($repos as $repo) {
        echo $repo, PHP_EOL;
      }
    }
    elseif (empty($repos) && empty($names_only)) {
      $output->writeln('<info>No repositories!</info>');
    }

    return Command::SUCCESS;
  }

  /**
   * {@inheritdoc}
   */
  protected function getReportData(OutputInterface $output): array {

    $paginator = new ResultPager($this->gh);
    $repos = $paginator->fetchAll($this->gh->api('repos'), 'org', [$this->org_name]);

    $names = array_column($repos, 'name');
    uasort($names, 'strcasecmp');

    return $names;
  }

}
