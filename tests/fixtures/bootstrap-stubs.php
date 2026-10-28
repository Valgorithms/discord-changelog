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

// This replaces the fixture's Composer autoloader. Never load the real watcher.

namespace Monolog\Handler {
    class StreamHandler
    {
        public function __construct(...$arguments)
        {
        }
    }
}

namespace Monolog {
    class Logger
    {
        public const DEBUG = 100;

        public function __construct(...$arguments)
        {
        }

        public function pushHandler($handler): void
        {
        }

        public function error(string $message): void
        {
            echo $message;
        }
    }
}

namespace Valgorithms\DiscordChangelog {
    class GitHubCommitWatcher
    {
        private array $configuration;

        public function __construct(string $webhook, string $repository, string $state, $logger)
        {
            $this->configuration = [$webhook, $repository, basename($state)];
        }

        public function run(): void
        {
            echo json_encode([
                'configuration' => $this->configuration,
                'value' => getenv('DOTENV_TEST_VALUE'),
                'empty' => getenv('DOTENV_TEST_EMPTY'),
                'env' => getenv('DOTENV_TEST_ENV'),
                'server' => getenv('DOTENV_TEST_SERVER'),
            ], JSON_THROW_ON_ERROR);
        }
    }
}
