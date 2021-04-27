<?php

declare(strict_types=1);

namespace App\Command;

use Carbon\Carbon;
use Github\ResultPager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ReportDeadRepos
 */
class ReportRepoInactive extends AbstractReportCommand {

  use RateLimit;
  use AllSlugs;

  /**
   * {@inheritdoc}
   */
  protected static $defaultName = 'report:repos:dead';

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setDescription('List inactive repositories')
      ->setHelp('Lists repositories that have not been modified in over 6 months.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {

    $output->writeLn('<info>Checking for inactive repositories.</info>');

    $inactive = $this->getReportData($output);
    if (!empty($inactive)) {
      usort($inactive, static function ($a, $b) {
        return strcasecmp($a[0], $b[0]);
      });

      $output->writeln('<error>The following are inactive!</error>');
      $this->tableOutput($output, ['Repository', 'Last pushed'], $inactive);
      $output->writeLn('These repositories may need to be archived and removed.');
    }
    else {
      $output->writeln('<info>All repos appear to be active!</info>');
    }

    return Command::SUCCESS;
  }

  /**
   * {@inheritdoc}
   */
  protected function getReportData(OutputInterface $output): array {
    $paginator = new ResultPager($this->gh);
    $repos = $paginator->fetchAll($this->gh->api('repos'), 'org', [$this->org_name]);

    $inactive = [];

    foreach ($repos as $repo) {
      $pushed_at = Carbon::createFromFormat('Y-m-d\TH:i:m\Z', $repo['pushed_at']);
      if (empty($repo['pushed_at']) || Carbon::now()->subMonths(6)->gt($pushed_at)) {
        $inactive[] = [
          $repo['name'],
          $pushed_at->ago(),
        ];
      }
    }
    return $inactive;
  }

}
