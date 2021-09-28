<?php

declare(strict_types=1);

namespace App\Command\Reports;

use Github\HttpClient\Message\ResponseMediator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class ReportOrphanTeams
 */
class ReportTeamOrphan extends AbstractReportCommand {

  /**
   * {@inheritdoc}
   */
  protected static $defaultName = 'teams:report:orphans';

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setDescription('List teams with no projects')
      ->setHelp('Lists teams that do not have any projects');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {

    $output->writeLn('<info>Checking for teams with no projects.</info>');
    $output->writeLn('This process can take a while.');

    $orphans = $this->getReportData($output);
    if (!empty($orphans)) {
      asort($orphans);
      $output->writeln('<error>The following teams do not have any repositories!</error>');
      $this->tableOutput($output, ['Team name'], $orphans);
    }
    else {
      $output->writeln('<info>All teams appear to have repositories!</info>');
    }

    return Command::SUCCESS;
  }

  /**
   * {@inheritdoc}
   */
  protected function getReportData(OutputInterface $output): array {
    $orphans = [];
    $slugs = $this->getAllSlugs($this->gh, 'teams', [$this->org_name]);

    $progress = new ProgressBar($output);
    foreach ($progress->iterate($slugs) as $team_name) {
      try {
        $this->rateLimit($this->gh);

        // The PHP library endpoint is outdated. This is the correct endpoint.
        $response = $this->gh->getHttpClient()
          ->get(sprintf('/orgs/%s/teams/%s/repos', $this->org_name, $team_name));
        $result = ResponseMediator::getContent($response);
      } catch (Throwable $x) {
        $output->writeLn('<error>An error was encountered while fetching a team.</error>');
        $output->writeLn($team_name . ': ' . $x->getMessage());
        continue;
      }

      if (empty($result)) {
        $orphans[] = $team_name;
      }
    }

    $output->writeln('');
    return $orphans;
  }

}
