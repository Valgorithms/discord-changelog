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

/**
 * Represents a GitHub commit with convenient accessors for common properties.
 *
 * @since 1.0
 */
class Commit implements \JsonSerializable
{
    public function __construct(protected array $data)
    {
    }

    public function getSha(): string
    {
        return $this->data['sha'] ?? '';
    }

    public function getMessage(): string
    {
        return $this->data['commit']['message'] ?? '';
    }

    public function getHtmlUrl(): string
    {
        return $this->data['html_url'] ?? '';
    }

    public function getAuthorName(): string
    {
        return $this->data['commit']['author']['name'] ?? ($this->data['author']['login'] ?? 'Unknown');
    }

    public function getTimestamp(): string
    {
        return $this->data['commit']['author']['date'] ?? date(DATE_ATOM);
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
