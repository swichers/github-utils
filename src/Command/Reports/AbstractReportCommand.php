<?php
declare(strict_types=1);

namespace App\Command\Reports;

use App\Command\AbstractCommand;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AbstractReportCommand
 */
abstract class AbstractReportCommand extends AbstractCommand {

  /**
   * Get data necessary for report output.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The console output interface.
   *
   * @return array
   *   An array of report data ready for use in a table.
   */
  abstract protected function getReportData(OutputInterface $output): array;

}
