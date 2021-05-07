<?php

declare(strict_types=1);

namespace App\Command\Reports;

use Carbon\Carbon;
use Github\ResultPager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class ReportDeadRepos
 */
class ReportRepoInactive extends AbstractReportCommand {

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

    $io = new SymfonyStyle($input, $output);

    $io->title('Scan for Inactive Repositories');

    $inactive = $this->getReportData($output);
    if (!empty($inactive)) {
      usort($inactive, static function ($a, $b) {
        return strcasecmp($a[0], $b[0]);
      });
      $io->table(['Repository', 'Last pushed'], $inactive);
    }
    else {
      $io->info('All repos appear to be active!');
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
      $pushed_at = Carbon::createFromFormat('Y-m-d\TH:i:s\Z', $repo['pushed_at']);
      $is_inactive = empty($repo['pushed_at']);
      $is_inactive |= $repo['archived'] ?: false;
      $is_inactive |= Carbon::now()->subMonths(6)->gt($pushed_at);
      if ($is_inactive) {
        $ago = $pushed_at->ago();

        if ($pushed_at->lte(Carbon::now()->subYears(2))) {
          $ago = '<error>' . $ago . '</error>';
        }
        elseif ($pushed_at->lte(Carbon::now()->subYear())) {
          $ago = '<comment>' . $ago . '</comment>';
        }

        $inactive[] = [$repo['name'], $ago];
      }
    }
    return $inactive;
  }

}
