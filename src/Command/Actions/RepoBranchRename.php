<?php

declare(strict_types=1);

namespace App\Command\Actions;

use App\Command\AbstractCommand;
use Github\ResultPager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class RepoBranchRename
 */
class RepoBranchRename extends AbstractCommand {

  /**
   * {@inheritdoc}
   */
  protected static $defaultName = 'repos:branch:rename';

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setDescription('Rename the given branch across all repositories')
      ->setHelp('Renames a branch from its current name to the given name. The idea is to ease migration from `master` to `main`.')
      ->addOption('from', 'f', InputOption::VALUE_REQUIRED, 'The starting branch name.')
      ->addOption('to', 't', InputOption::VALUE_REQUIRED, 'The final branch name.')
      ->addOption('create-instead', 'c', InputOption::VALUE_NONE, 'Create a new branch instead of renaming the existing branch.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {

    $starting_branch = $input->getOption('from');
    $ending_branch = $input->getOption('to');

    if ($starting_branch === $ending_branch) {
      $output->writeln('<comment>Starting and ending branch names were the same.</comment>');
      return Command::INVALID;
    }

    $paginator = new ResultPager($this->gh);
    $repos = $paginator->fetchAll($this->gh->api('repos'), 'org', [$this->org_name]);
    foreach ($repos as $repo) {

      if ($repo['archived']) {
        continue;
      }

      $branches_detailed = $this->gh->repo()
        ->branches($this->org_name, $repo['name']);
      $branches_detailed = array_combine(array_column($branches_detailed, 'name'), $branches_detailed);

      $has_starting = isset($branches_detailed[$starting_branch]);
      $has_ending = isset($branches_detailed[$ending_branch]);

      if ($has_starting && $has_ending) {
        $this->handleBothExist($output, $repo['name'], $starting_branch, $ending_branch);
      }
      elseif (!$has_starting && !$has_ending) {
        $this->handleBothMissing($output, $repo['name'], $starting_branch, $ending_branch);
      }
      elseif (!$has_starting && $has_ending) {
        $this->handleStartMissing($output, $repo['name'], $starting_branch, $ending_branch);
      }
      elseif ($has_starting && !$has_ending) {
        $this->handleProcess($output, $repo['name'], $starting_branch, $ending_branch, $branches_detailed, $input->getOption('create-instead'));
      }
    }

    $output->writeLn('<info>Done</info>');

    return Command::SUCCESS;
  }

  /**
   * Handles when both branches already exist.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The console output object.
   * @param string $repository
   *   The repository name.
   * @param string $startingBranch
   *   The branch we are starting with.
   * @param string $endingBranch
   *   The branch we are ending with.
   */
  protected function handleBothExist(OutputInterface $output, string $repository, string $startingBranch, string $endingBranch): void {
    $output->writeLn(sprintf('<error>%s has both %s and %s branches!</error>', $repository, $startingBranch, $endingBranch));
  }

  /**
   * Handles with both branches exist.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The console output object.
   * @param string $repository
   *   The repository name.
   * @param string $startingBranch
   *   The branch we are starting with.
   * @param string $endingBranch
   *   The branch we are ending with.
   */
  protected function handleBothMissing(OutputInterface $output, string $repository, string $startingBranch, string $endingBranch): void {
    $output->writeLn(sprintf('<error>%s is missing both %s and %s branches!</error>', $repository, $startingBranch, $endingBranch));
  }

  /**
   * Handles when the starting branch is missing.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The console output object.
   * @param string $repository
   *   The repository name.
   * @param string $startingBranch
   *   The branch we are starting with.
   * @param string $endingBranch
   *   The branch we are ending with.
   */
  protected function handleStartMissing(OutputInterface $output, string $repository, string $startingBranch, string $endingBranch): void {
    $output->writeLn(sprintf('<comment>%s already has the correct branch setup.</comment>', $repository));
  }

  /**
   * Rename or create the branches as needed.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The console output object.
   * @param string $repository
   *   The repository name.
   * @param string $startingBranch
   *   The branch we are starting with.
   * @param string $endingBranch
   *   The branch we are ending with.
   * @param array $branchInfo
   *   All of the branch information for the repository.
   * @param bool|false $createInstead
   *   TRUE to create a new branch instead of renaming an existing branch.
   */
  protected function handleProcess(OutputInterface $output, string $repository, string $startingBranch, string $endingBranch, array $branchInfo, bool $createInstead = FALSE): void {
    if ($createInstead) {
      $this->handleCreate($output, $repository, $startingBranch, $endingBranch, $branchInfo);
      return;
    }

    $output->writeLn(sprintf('%s is being migrated from %s to %s', $repository, $startingBranch, $endingBranch));

    $uri_parts = [
      rawurlencode($this->org_name),
      rawurlencode($repository),
      rawurlencode($startingBranch),
    ];

    $this->gh
      ->getHttpClient()
      ->post(vsprintf('/repos/%s/%s/branches/%s/rename', $uri_parts), [], json_encode(['new_name' => $endingBranch], JSON_THROW_ON_ERROR));
  }

  /**
   * Create a new branch from the given start branch.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The console output object.
   * @param string $repository
   *   The repository name.
   * @param string $startingBranch
   *   The branch we are starting with.
   * @param string $endingBranch
   *   The branch we are ending with.
   * @param array $branchInfo
   *   All of the branch information for the repository.
   */
  protected function handleCreate(OutputInterface $output, string $repository, string $startingBranch, string $endingBranch, array $branchInfo): void {
    $output->writeLn(sprintf('%s is creating %s from %s', $repository, $endingBranch, $startingBranch));

    $this->gh->gitData()->references()->create($this->org_name, $repository, [
      'ref' => 'refs/heads/' . $endingBranch,
      'sha' => $branchInfo[$startingBranch]['commit']['sha'],
    ]);
  }

}
