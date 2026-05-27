#!/usr/bin/env php
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

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Valgorithms\DiscordChangelog\GitHubCommitWatcher;

// Find composer autoload path from several common locations. Avoid passing false to file_exists.
static $autoload_candidates = [
    __DIR__.'/vendor/autoload.php',
    dirname(__DIR__).'/vendor/autoload.php',
    realpath(__DIR__.'/../vendor/autoload.php'),
    realpath(__DIR__.'/../../vendor/autoload.php'),
    realpath(dirname(__DIR__).'/../vendor/autoload.php'),
    realpath(dirname(__DIR__).'/../../vendor/autoload.php'),
];

$autoload_path = null;
foreach ($autoload_candidates as $candidate) {
    if ($candidate && is_string($candidate) && file_exists($candidate)) {
        $autoload_path = $candidate;
        break;
    }
}

if (! $autoload_path) {
    throw new \Exception('Composer autoloader not found. Run `composer update` and try again.');
}
require $autoload_path;

function loadEnv(string $filePath): void
{
    if (! file_exists($filePath)) {
        throw new \Exception('The .env file does not exist.');
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $trimmedLines = array_map('trim', $lines);
    $filteredLines = array_filter($trimmedLines, fn ($line) => $line && ! str_starts_with($line, '#'));

    array_walk($filteredLines, function ($line) {
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if (! array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
        }
    });
}

static $env_candidates = [
    getcwd().'/.env',
    dirname(getcwd()).'/.env',
    realpath(getcwd().'/../.env') ?: '',
    realpath(getcwd().'/../../.env') ?: '',
    realpath(dirname(getcwd()).'/../.env') ?: '',
    realpath(dirname(getcwd()).'/../../.env') ?: '',
    __DIR__.'/.env',
    dirname(__DIR__).'/ .env',
    dirname(__DIR__).'/../.env',
];

$env_path = null;
foreach ($env_candidates as $candidate) {
    if ($candidate && is_string($candidate) && file_exists($candidate)) {
        $env_path = $candidate;
        break;
    }
}

if (! $env_path) {
    throw new \Exception('The .env file does not exist. Please create one in the root directory.');
}
loadEnv($env_path);

$webhookUrl = getenv('DISCORD_WEBHOOK') ?: $_SERVER['DISCORD_WEBHOOK'] ?? '';
$repoApiUrl = getenv('GITHUB_REPO_API') ?: $_SERVER['GITHUB_REPO_API'] ?? '';
$lastCommitFileName = getenv('LAST_COMMIT_FILE') ?: $_SERVER['LAST_COMMIT_FILE'] ?? 'last_commit.txt';
$lastCommitFile = __DIR__.'/'.$lastCommitFileName;

// Ensure the last-commit file exists so subsequent file operations don't fail
$lastCommitDir = dirname($lastCommitFile);
if (! is_dir($lastCommitDir)) {
    @mkdir($lastCommitDir, 0755, true);
}
if (! file_exists($lastCommitFile)) {
    @file_put_contents($lastCommitFile, '');
	@chmod($lastCommitFile, 0644);
}

$logger = new Logger('discord-changelog');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

if ($webhookUrl === 'CHANGEME') {
    $logger->error('DISCORD_WEBHOOK is set to placeholder value CHANGEME; please set a valid webhook in .env');
    exit(1);
}

if (empty($webhookUrl) || empty($repoApiUrl)) {
    $logger->error('Please set DISCORD_WEBHOOK and GITHUB_REPO_API in your .env');
    exit(1);
}

$watcher = new GitHubCommitWatcher($webhookUrl, $repoApiUrl, $lastCommitFile, $logger);
$watcher->run();
