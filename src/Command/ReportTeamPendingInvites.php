<?php

declare(strict_types=1);

namespace App\Command;

use Carbon\Carbon;
use Github\HttpClient\Message\ResponseMediator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class ReportTeamPendingInvites
 */
class ReportTeamPendingInvites extends AbstractReportCommand {

  use RateLimit;
  use AllSlugs;

  /**
   * {@inheritdoc}
   */
  protected static $defaultName = 'report:teams:pending-invites';

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setDescription('List teams with pending invites')
      ->setHelp('Lists teams that have pending invites');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {

    $output->writeLn('<info>Checking for teams with pending invites.</info>');
    $output->writeLn('This process can take a while.');

    $orphans = $this->getReportData($output);
    if (!empty($orphans)) {
      asort($orphans);
      $output->writeln('<error>The following teams have pending invites!</error>');
      $this->tableOutput($output, ['Team', 'User', 'When'], $orphans);
    }
    else {
      $output->writeln('<info>All teams appear to be invite free!</info>');
    }

    return Command::SUCCESS;
  }

  /**
   * {@inheritdoc}
   */
  protected function getReportData(OutputInterface $output): array {

    $invites = [];

    $slugs = $this->getAllSlugs($this->gh, 'teams');
    $progress = new ProgressBar($output);
    foreach ($progress->iterate($slugs) as $team_name) {
      try {
        $this->rateLimit($this->gh);

        $response = $this->gh->getHttpClient()
          ->get(sprintf('/orgs/%s/teams/%s/invitations', $this->org_name, $team_name));
        $result = ResponseMediator::getContent($response);
      } catch (Throwable $x) {
        $output->writeLn('<error>An error was encountered while fetching invites.</error>');
        $output->writeLn($team_name . ': ' . $x->getMessage());
        continue;
      }

      if (!empty($result)) {
        foreach ($result as $invite) {
          $invites[] = [
            $team_name,
            $invite['login'],
            Carbon::parse($invite['created_at'])->ago(),
          ];
        }
      }
    }

    $output->writeln('');
    return $invites;
  }

}
