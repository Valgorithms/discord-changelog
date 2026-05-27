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

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Valgorithms\DiscordChangelog\Commit;
use Valgorithms\DiscordChangelog\GitHubCommitWatcher;

final class GitHubCommitWatcherTest extends TestCase
{
    public function testGetNewCommitsWhenNoLastSeen(): void
    {
        $watcher = new GitHubCommitWatcher('', '', __DIR__.'/../last_commit.txt', new NullLogger());

        $commits = [
            ['sha' => 'a1', 'commit' => ['message' => 'm1']],
            ['sha' => 'a2', 'commit' => ['message' => 'm2']],
        ];

        $result = $watcher->getNewCommits($commits, '');
        $this->assertCount(1, $result);
        $this->assertSame('a1', $result[0]['sha']);
    }

    public function testGetNewCommitsSinceLastSeen(): void
    {
        $watcher = new GitHubCommitWatcher('', '', __DIR__.'/../last_commit.txt', new NullLogger());

        $commits = [
            ['sha' => 'c3', 'commit' => ['message' => 'm3']],
            ['sha' => 'c2', 'commit' => ['message' => 'm2']],
            ['sha' => 'c1', 'commit' => ['message' => 'm1']],
        ];

        $result = $watcher->getNewCommits($commits, 'c1');
        $this->assertCount(2, $result);
        $this->assertSame('c3', $result[0]['sha']);
        $this->assertSame('c2', $result[1]['sha']);
    }

    public function testShouldSendCommitFiltersSocialSdk(): void
    {
        $watcher = new GitHubCommitWatcher('', '', __DIR__.'/../last_commit.txt', new NullLogger());

        $commitAllowed = new Commit(['sha' => 'ok', 'commit' => ['message' => 'Normal change']]);
        $commitFiltered = new Commit(['sha' => 'bad', 'commit' => ['message' => 'Update Social SDK code']]);

        $this->assertTrue($watcher->shouldSendCommit($commitAllowed));
        $this->assertFalse($watcher->shouldSendCommit($commitFiltered));
    }
}
