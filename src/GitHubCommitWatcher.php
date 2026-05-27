<?php

declare(strict_types=1);

/*
 * This file is a part of the Discord Changelog project.
 *
 * Copyright (c) 2026-present Valithor Obsidion <valithor@discordphp.org>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

namespace Valgorithms\DiscordChangelog;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Watches for new commits to the discord-api-docs repository and sends notifications to a Discord webhook.
 *
 * @since 1.0
 */
class GitHubCommitWatcher
{
    public const GITHUB_REPO_API_DEFAULT = 'https://api.github.com/repos/discord/discord-api-docs/commits';
    public const LAST_COMMIT_FILE_DEFAULT = 'last_commit.txt';
    public const HTTP_GET_OPTIONS = [
        'http' => [
            'method' => 'GET',
            'header' => 'User-Agent: DiscordCommitWatcher'.PHP_EOL,
        ],
    ];

    public function __construct(
        protected string $webhookUrl,
        protected string $repoApiUrl = self::GITHUB_REPO_API_DEFAULT,
        protected string $lastCommitFile = self::LAST_COMMIT_FILE_DEFAULT,
        protected ?LoggerInterface $logger = null
    ) {
        if ($logger === null) {
            $logger = new Logger('discord-changelog');
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
            $this->logger = $logger;
        }
    }

    /**
     * Runs the commit watcher, fetching new commits and sending them to the webhook if applicable.
     */
    public function run(): void
    {
        $commitsData = $this->fetchCommits();

        if (empty($commitsData) || ! isset($commitsData[0]['sha'])) {
            $this->logger->warning('No commits found or invalid response.');

            return;
        }

        $lastSeenSha = file_exists($this->lastCommitFile) ? trim(file_get_contents($this->lastCommitFile)) : '';

        $new = $this->getNewCommits($commitsData, $lastSeenSha);
        if (empty($new)) {
            $this->logger->info('No new commits.');

            return;
        }

        $sent = 0;
        foreach (array_reverse($new) as $commitArr) {
            if ($sent > 0 && $sent % 5 === 0) {
                sleep(5);
            }

            $commit = new Commit($commitArr);

            if (! $this->shouldSendCommit($commit)) {
                $this->logger->info('Skipping commit (filtered)', ['sha' => $commit->getSha()]);
                $sent++;
                continue;
            }

            $sent += $this->sendWebhook($commit) ? 1 : 0;
        }

        // Save latest SHA (most recent commit)
        file_put_contents($this->lastCommitFile, $commitsData[0]['sha']);
    }

    /**
     * Fetches commits from the GitHub API. Returns an array of commit data or an empty array on failure.
     *
     * @return array Commits, or empty array on failure
     */
    public function fetchCommits(): array
    {
        $context = stream_context_create(self::HTTP_GET_OPTIONS);
        $response = @file_get_contents($this->repoApiUrl, false, $context);
        if ($response === false) {
            $this->logger->error('Failed to fetch commits.');

            return [];
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Returns an array of new commits that have not been seen since the last seen SHA. If no last seen SHA is provided, returns the most recent commit.
     *
     * @param array  $commits     Array of commits
     * @param string $lastSeenSha The SHA of the last seen commit
     *
     * @return array New commits that have not been seen since the last seen SHA
     */
    public function getNewCommits(array $commits, string $lastSeenSha): array
    {
        if (! $lastSeenSha) {
            return [$commits[0]];
        }

        $new = [];
        foreach ($commits as $commit) {
            if (($commit['sha'] ?? '') === $lastSeenSha) {
                break;
            }
            $new[] = $commit;
        }

        return $new;
    }

    /**
     * Determines if a commit should be sent to the webhook based on its message content.
     *
     * Commits containing "Social SDK" (case-insensitive) are filtered out.
     *
     * @param Commit $commit The commit to evaluate
     *
     * @return bool True if the commit should be sent, false if it should be filtered out
     */
    public function shouldSendCommit(Commit $commit): bool
    {
        // Skip commits containing "Social SDK" (case-insensitive)
        $message = $commit->getMessage();
        if (stripos($message, 'Social SDK') !== false) {
            return false;
        }

        return true;
    }

    /**
     * Sends a webhook notification for a given commit. Returns true on success, false on failure.
     *
     * @param Commit $commit The commit for which to send the webhook
     *
     * @return bool True if the webhook was sent successfully, false otherwise
     */
    public function sendWebhook(Commit $commit): bool
    {
        $message = mb_strimwidth($commit->getMessage(), 0, 250, '…');
        $payload = [
            'content' => '🆕 **New commit to `discord-api-docs`!**',
            'embeds' => [[
                'title' => $message,
                'url' => $commit->getHtmlUrl(),
                'author' => [
                    'name' => $commit->getAuthorName(),
                ],
                'timestamp' => $commit->getTimestamp(),
            ]],
        ];

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json'.PHP_EOL.'User-Agent: DiscordCommitWatcher'.PHP_EOL,
                'content' => json_encode($payload),
            ],
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($this->webhookUrl, false, $context);
        if ($response === false) {
            $this->logger->error('Failed to send webhook for commit.', ['sha' => $commit->getSha()]);

            return false;
        }

        $this->logger->info('Webhook sent for commit.', ['sha' => $commit->getSha()]);
        $this->logger->debug('Commit raw data', ['raw' => $commit->jsonSerialize()]);

        return true;
    }
}
