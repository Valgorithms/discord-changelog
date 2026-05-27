Discord changelog webhook watcher

Small PSR-4 CLI that watches a GitHub repo's commits and posts new commits
to a Discord webhook (skips commits that mention "Social SDK").

Quick start

- Install dependencies:

```bash
composer install
```
- Copy and edit environment variables:

```bash
cp .env.example .env
# Edit .env and set DISCORD_WEBHOOK and GITHUB_REPO_API
```
-- Run the CLI (from the project root):

```bash
# Preferred: run the bootstrap which locates .env
php run.php
```

Bootstrap helper

- `run.php` is the project's bootstrap. It searches parent directories for a
	`.env` and ensures `last_commit.txt` exists; run it with `php run.php`.

Configuration

- Environment variables are defined in `.env` (see [.env.example](.env.example)).
	- `DISCORD_WEBHOOK` — your Discord webhook URL (don't leave as `CHANGEME`).
	- `GITHUB_REPO_API` — GitHub commits API URL.
	- `LAST_COMMIT_FILE` — file to store last seen commit SHA (defaults to `last_commit.txt`).

Composer scripts

- `composer unit` — run the test suite (`phpunit`).
- `composer cs` — run PHP CS Fixer.
- `composer phpacker` — build single-file binaries (non-interactive; platform baked into the script).

Testing

- Tests live under `tests/` and use PHPUnit. Run:

```bash
composer unit
```

Packaging notes

- The `phpacker` build bundles a bootstrap (`run.php`) and the packaged binary
	expects to find a `.env` when executed; `run.php` searches upward from the
	current working directory so packaged binaries can locate the project `.env`.

Logging

- The CLI uses Monolog and logs to `php://stdout` by default. To provide a
	custom logger, instantiate `Valgorithms\DiscordChangelog\GitHubCommitWatcher`
	directly and pass a PSR-3 `LoggerInterface` implementation.

Support

- If you hit issues packaging or running the exe, ensure the build output
	contains the `vendor/` folder or use `run.php` which is tolerant of layout.

