<?php

declare(strict_types=1);

namespace App\Command;

use Github\Client;
use Github\HttpClient\Message\ResponseMediator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class RepoConvert
 */
class RepoConvert extends Command {

  /**
   * {@inheritdoc}
   */
  protected static $defaultName = 'repos:migrate:teams';

  /**
   * The permission access hierarchy.
   *
   * More permissive should come before least permissive.
   *
   * @var string[]
   */
  protected static $permissionHierarchy = ['admin', 'push', 'pull'];

  /**
   * Teams to ignore when migrating users to repos.
   *
   * @var string[]
   */
  protected static $ignoredTeams = [];

  /**
   * A GitHub client instance.
   *
   * @var \Github\Client
   */
  protected $gh;

  /**
   * The GitHub organization name.
   *
   * @var string
   */
  protected $org_name;

  /**
   * AbstractReportCommand constructor.
   *
   * @param \Github\Client $client
   *  A ready-to-go GitHub client.
   * @param string $organizationName
   *   The GitHub organization to run reports against.
   * @param string|null $name
   *   The command name.
   */
  public function __construct(Client $client, string $organizationName, string $name = NULL) {
    parent::__construct($name);

    $this->gh = $client;
    $this->org_name = $organizationName;
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setDescription('Migrate users from teams to repo members')
      ->setHelp('Migrates the repository\'s teams into users with the same access.')
      ->addArgument('repository', InputArgument::REQUIRED, 'What repository should be migrated?')
      ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Assume yes to all prompts')
      ->addOption('exclude-team', 'x', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Exclude this team from processing. Option can be used multiple times in one call.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {

    $yes_prompt = $input->getOption('yes') ?? FALSE;
    $excluded_teams = $input->getOption('exclude-team') ?? [];

    $repository = $input->getArgument('repository');
    $repository = preg_replace('/^' . preg_quote($this->org_name, '/') . '\//', '', $repository);

    $io = new SymfonyStyle($input, $output);

    $teams = $this->mapTeamsToMembers($repository, $excluded_teams);
    if (empty($teams)) {
      $io->warning('This repository has no teams.');
      return Command::FAILURE;
    }

    $permissions = $this->mapMembersToPermissions($repository, $excluded_teams);
    if (empty($permissions)) {
      $io->warning('The teams have no members.');
      return Command::FAILURE;
    }

    asort($permissions);

    $io->title('GitHub Team to Member Migration');
    $io->text([
      'This command will:',
      '',
      '* Migrate repository team members directly to the repository',
      '* Remove teams from that repository',
      '* Delete those teams which no longer have any associated repository',
    ]);

    $io->section('Teams');
    $io->listing(array_keys($teams));

    // Wrap each item in an array so we can make a nice display.
    $formatted = $permissions;
    array_walk($formatted, static function (&$value, $key) {
      $value = [$key => $value];
    });
    $formatted = array_values($formatted);
    $io->definitionList('Final permission mapping', new TableSeparator(), ...$formatted);

    if (!$yes_prompt && !$io->confirm('Do you wish to continue?')) {
      return Command::FAILURE;
    }

    foreach ($permissions as $member => $permission) {
      $io->text([
        'Migrating ' . $member,
        sprintf('..Adding with %s permission', $permission),
      ]);
      $io->newLine();

      $this->gh->repository()
        ->collaborators()
        ->add($this->org_name, $repository, $member, ['permission' => $permission]);
    }

    $io->warning('The following checks are unreliable due to endpoint caching. Additional followup may be necessary.');

    $io->section('Removing team access');
    foreach (array_keys($teams) as $team) {
      $io->text(sprintf('Removing %s access', $team));
      $this->gh->getHttpClient()->delete(sprintf('orgs/%s/teams/%s/repos/%s/%s', $this->org_name, $team, $this->org_name, $repository));
    }

    $io->section('Checking for teams with no repository access');
    foreach (array_keys($teams) as $team) {
      $response = $this->gh->getHttpClient()->get(sprintf('orgs/%s/teams/%s/repos', $this->org_name, $team));
      $repos = ResponseMediator::getContent($response);
      if (!empty($repos)) {
        $io->text(sprintf('Skipped: %s still has repositories.', $team));
      }
      else {
        $io->text(sprintf('Removed: %s had no repositories.', $team));
        $this->gh->team()->remove($team, $this->org_name);
      }
    }

    $io->success('Done!');

    return Command::SUCCESS;
  }

  /**
   * Map repository members to their teams.
   *
   * @param string $repository
   *   The repository to pull information for.
   * @param string[] $excludedTeams
   *   A list of team names to exclude.
   *
   * @return array|string[][]
   *   An array of usernames keyed by team name.
   */
  protected function mapTeamsToMembers(string $repository, array $excludedTeams = []): array {
    $teams = $this->getTeams($repository, $excludedTeams);

    $result = [];
    foreach ($teams as $team) {

      $members = array_column($this->gh->team()
        ->members($team['slug'], $this->org_name), 'login');
      $result[$team['slug']] = $members;
    }
    return $result;
  }

  /**
   * Map repository members to the permission they should have.
   *
   * @param string $repository
   *   The repository to pull information for.
   * @param string[] $excludedTeams
   *   A list of team names to exclude.
   *
   * @return array|string[]
   *   An array of permission names keyed by username.
   */
  protected function mapMembersToPermissions(string $repository, array $excludedTeams = []): array {
    $teams = $this->getTeams($repository, $excludedTeams);

    $result = [];
    foreach ($teams as $team) {

      $members = array_column($this->gh->team()
        ->members($team['slug'], $this->org_name), 'login');

      $permission = 'pull';
      foreach (self::$permissionHierarchy as $key) {
        if (!empty($team['permissions'][$key])) {
          $permission = $key;
          break;
        }
      }

      foreach ($members as $member) {
        $result[$member][] = $permission;
      }
    }

    // Convert array of permissions into the highest level permission.
    foreach ($result as $member => $permissions) {
      foreach (self::$permissionHierarchy as $key) {
        if (in_array($key, $permissions, TRUE)) {
          $result[$member] = $key;
          break;
        }
      }
    }

    return $result;
  }

  /**
   * Get the list of teams from a repository.
   *
   * @param string $repository
   *   The repository to get team data for.
   * @param string[] $excludedTeams
   *   A list of team names to exclude.
   *
   * @return array
   *   The team data.
   */
  protected function getTeams(string $repository, array $excludedTeams = []): array {
    $teams = $this->gh->repository()->teams($this->org_name, $repository);

    // Exclude ignored teams from our decision making.
    return array_filter($teams, static function ($item) use ($excludedTeams) {
      return !in_array($item['slug'], array_merge(self::$ignoredTeams, $excludedTeams), TRUE);
    });
  }

}
