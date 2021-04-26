<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class ReportEmptyRepos
 */
class ReportRepoEmpty extends AbstractReportCommand {

  use RateLimit;
  use AllSlugs;

  /**
   * {@inheritdoc}
   */
  protected static $defaultName = 'report:repos:empty';

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setDescription('List repositories with no code')
      ->setHelp('Lists repositories that were never used');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {

    $output->writeLn('<info>Checking for repositories with no code.</info>');
    $output->writeLn('This process can take a while.');

    $empty_repos = $this->getReportData($output);
    if (!empty($empty_repos)) {
      asort($empty_repos);
      $this->tableOutput($output, ['Repository'], $empty_repos);
    }
    else {
      $output->writeln('<info>All repos appear to have code!</info>');
    }

    return Command::SUCCESS;
  }

  /**
   * {@inheritdoc}
   */
  protected function getReportData(OutputInterface $output): array {
    $slugs = $this->getAllSlugs($this->gh, 'repos', 'org', 'name');

    $empty_repos = [];

    $progress = new ProgressBar($output);
    foreach ($progress->iterate($slugs) as $repo_name) {
      try {
        $this->rateLimit($this->gh);
        $result = $this->gh->repo()->contributors($this->org_name, $repo_name);
      } catch (Throwable $x) {
        $output->writeLn('<error>An error was encountered while fetching a repo.</error>');
        $output->writeLn($repo_name . ': ' . $x->getMessage());
        continue;
      }

      if (empty($result)) {
        $empty_repos[] = $repo_name;
      }
    }

    $output->writeln('');

    return $empty_repos;
  }

}
