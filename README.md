# GitHub Reports and Tasks

Symfony based GitHub report and task runner.

## Installation

```
git clone git@github.com:swichers/github-utils.git
cd github-utils
composer install
cp config/example.local.config.yml config/local.config.yml
nano config/local.config.yml
```

Add your GitHub API key and organization name.

## Usage

Run `./bin/gh-utils` for a list of available commands.

## Available commands
```
 repos
  repos:autotag                 Automatically tag repositories
  repos:branch:default          Sets the default branch across all repositories
  repos:branch:rename           Rename the given branch across all repositories
  repos:migrate:teams           Migrate users from teams to repo members
  repos:report:empty            List repositories with no code
  repos:report:inactive         List inactive repositories
  repos:report:list             List all repositories
  repos:report:pending-invites  List repos with pending invites
 teams
  teams:report:bad-names        List improperly named teams
  teams:report:empty            List teams with no members
  teams:report:orphans          List teams with no projects
  teams:report:pending-invites  List teams with pending invites
```
