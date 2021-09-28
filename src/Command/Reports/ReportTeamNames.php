<?php

declare(strict_types=1);

namespace App\Command\Reports;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Symfony\Component\String\s;

/**
 * Class ReportBadNames
 */
class ReportTeamNames extends AbstractReportCommand {

  /**
   * {@inheritdoc}
   */
  protected static $defaultName = 'teams:report:bad-names';

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setDescription('List improperly named teams')
      ->setHelp('Lists teams that do not match the expected group name pattern of {name}-{permission}');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {

    $bad_names = $this->getReportData($output);
    if (!empty($bad_names)) {
      $output->writeln('<error>The following team names do not match the expected pattern.</error>');
      $this->tableOutput($output, ['Team name'], $bad_names);
      $output->writeln('They should be renamed to match the {name}-{permission} pattern.');
    }
    else {
      $output->writeln('<info>All names appear to match the expected syntax!</info>');
    }

    return Command::SUCCESS;
  }

  /**
   * {@inheritdoc}
   */
  protected function getReportData(OutputInterface $output): array {
    $expected_suffixes = [
      'admin',
      'pull',
      'push',
    ];

    $slugs = $this->getAllSlugs($this->gh, 'teams', [$this->org_name]);

    return array_filter($slugs, static function ($name) use ($expected_suffixes) {
      return !s($name)->lower()->endsWith($expected_suffixes);
    });
  }

}
