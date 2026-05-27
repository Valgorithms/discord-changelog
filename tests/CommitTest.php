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
use Valgorithms\DiscordChangelog\Commit;

final class CommitTest extends TestCase
{
    public function testAccessors(): void
    {
        $data = [
            'sha' => 'abc123',
            'commit' => [
                'message' => 'Test commit message',
                'author' => [
                    'name' => 'Alice',
                    'date' => '2020-01-01T00:00:00Z',
                ],
            ],
            'html_url' => 'https://example.com/commit/abc123',
            'author' => ['login' => 'alice'],
        ];

        $commit = new Commit($data);

        $this->assertSame('abc123', $commit->getSha());
        $this->assertSame('Test commit message', $commit->getMessage());
        $this->assertSame('https://example.com/commit/abc123', $commit->getHtmlUrl());
        $this->assertSame('Alice', $commit->getAuthorName());
        $this->assertSame('2020-01-01T00:00:00Z', $commit->getTimestamp());
        $this->assertSame($data, $commit->jsonSerialize());
    }

    public function testAuthorFallback(): void
    {
        $data = [
            'sha' => 'def456',
            'commit' => [
                'message' => 'No author login',
                'author' => [
                    'name' => 'Bob',
                ],
            ],
        ];

        $commit = new Commit($data);
        $this->assertSame('Bob', $commit->getAuthorName());
    }
}
