<?php

declare(strict_types=1);

namespace App\Command\Actions;

use App\Command\AbstractCommand;
use Github\ResultPager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class RepoBranchDefault
 */
class RepoBranchDefault extends AbstractCommand {

  /**
   * {@inheritdoc}
   */
  protected static $defaultName = 'repos:branch:default';

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setDescription('Sets the default branch across all repositories')
      ->setHelp('Changes the default branch across every repository on the organization.')
      ->addArgument('branch', InputArgument::REQUIRED, 'What branch should be set as default?');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {

    $default_branch = $input->getArgument('branch');

    $paginator = new ResultPager($this->gh);
    $repos = $paginator->fetchAll($this->gh->api('repos'), 'org', [$this->org_name]);
    foreach ($repos as $repo) {

      $output->write(sprintf('Changing default branch for %s: ', $repo['name']));

      if ($repo['archived']) {
        $output->writeln('<comment>archived</comment>');
        continue;
      }

      if ($repo['default_branch'] === $default_branch) {
        $output->writeLn('<comment>no change</comment>');
        continue;
      }

      $this->gh->repo()->update($this->org_name, $repo['name'], ['default_branch' => $default_branch]);
      $output->writeLn('<info>done</info>');
    }

    $output->writeLn('<info>Done</info>');

    return Command::SUCCESS;
  }
}
