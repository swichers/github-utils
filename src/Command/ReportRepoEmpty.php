<?php

declare(strict_types=1);

namespace App\Command;

use Github\ResultPager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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

    $empty_repos = $this->getReportData($output);
    if (!empty($empty_repos)) {
      asort($empty_repos);
      $output->writeln('');
      $this->tableOutput($output, ['Repository'], $empty_repos);
      $output->writeln('');
      $output->writeln('<comment>These repositories may not have code, or may only contain a README.</comment>');
      $output->writeln('<comment>They should be manually reviewed before any permanent action is taken.</comment>');
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

    $paginator = new ResultPager($this->gh);
    $repos = $paginator->fetchAll($this->gh->api('repos'), 'org', [$this->org_name]);

    return array_column(array_filter($repos, static function ($repo) {
      $probably_empty = empty($repo['pushed_at']);
      $probably_empty |= $repo['pushed_at'] === $repo['created_at'];
      $probably_empty |= $repo['updated_at'] === $repo['created_at'];
      $probably_empty |= $repo['size'] <= 0;

      return $probably_empty;
    }), 'name');
  }

}
