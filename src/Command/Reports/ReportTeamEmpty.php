<?php

declare(strict_types=1);

namespace App\Command\Reports;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class ReportEmptyTeams
 */
class ReportTeamEmpty extends AbstractReportCommand {

  /**
   * {@inheritdoc}
   */
  protected static $defaultName = 'teams:report:empty';

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setDescription('List teams with no members')
      ->setHelp('Lists teams that do not have any members');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {

    $output->writeLn('<info>Checking for teams with no members.</info>');
    $output->writeLn('This process can take a while.');

    $empty_teams = $this->getReportData($output);
    if (!empty($empty_teams)) {
      asort($empty_teams);
      $output->writeln('<error>The following teams do not have any members!</error>');
      $this->tableOutput($output, ['Team name'], $empty_teams);
    }
    else {
      $output->writeln('<info>All teams appear to have members!</info>');
    }

    return Command::SUCCESS;
  }

  /**
   * {@inheritdoc}
   */
  protected function getReportData(OutputInterface $output): array {
    $slugs = $this->getAllSlugs($this->gh, 'teams');

    $empty_teams = [];

    $progress = new ProgressBar($output);
    foreach ($progress->iterate($slugs) as $team_name) {
      try {
        $this->rateLimit($this->gh);
        $result = $this->gh->team()->members($team_name, $this->org_name);
      } catch (Throwable $x) {
        $output->writeLn('<error>An error was encountered while fetching a team.</error>');
        $output->writeLn($team_name . ': ' . $x->getMessage());
        continue;
      }

      if (empty($result)) {
        $empty_teams[] = $team_name;
      }
    }

    $output->writeln('');

    return $empty_teams;
  }

}
