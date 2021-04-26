<?php

declare(strict_types=1);

namespace App\Command;

use Carbon\Carbon;
use Github\HttpClient\Message\ResponseMediator;
use Github\ResultPager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class ReportRepoPendingInvites
 */
class ReportRepoPendingInvites extends AbstractReportCommand {

  use RateLimit;
  use AllSlugs;

  /**
   * {@inheritdoc}
   */
  protected static $defaultName = 'report:repos:pending-invites';

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setDescription('List repos with pending invites')
      ->setHelp('Lists repos that have pending invites');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {

    $output->writeLn('<info>Checking for repos with pending invites.</info>');
    $output->writeLn('This process can take a while.');

    $orphans = $this->getReportData($output);
    if (!empty($orphans)) {
      asort($orphans);
      $output->writeln('<error>The following repos have pending invites!</error>');
      $this->tableOutput($output, ['Repository', 'User', 'When'], $orphans);
    }
    else {
      $output->writeln('<info>All repos appear to be invite free!</info>');
    }

    return Command::SUCCESS;
  }

  /**
   * {@inheritdoc}
   */
  protected function getReportData(OutputInterface $output): array {

    $invites = [];

    $paginator = new ResultPager($this->gh);
    $repos = $paginator->fetchAll($this->gh->api('repos'), 'org', [$this->org_name]);

    $progress = new ProgressBar($output);
    foreach ($progress->iterate($repos) as $repo) {
      try {
        $this->rateLimit($this->gh);

        $response = $this->gh->getHttpClient()
          ->get(sprintf('/repos/%s/%s/invitations', $this->org_name, $repo['name']));
        $result = ResponseMediator::getContent($response);
      } catch (Throwable $x) {
        $output->writeLn('<error>An error was encountered while fetching invites.</error>');
        $output->writeLn($repo['name'] . ': ' . $x->getMessage());
        continue;
      }

      if (!empty($result)) {
        foreach ($result as $invite) {
          $invites[] = [
            $repo['name'],
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
