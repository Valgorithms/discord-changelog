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

final class BootstrapEnvTest extends TestCase
{
    private string $directory;
    private string $project;
    private string $cwd;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/changelog-env-'.bin2hex(random_bytes(8));
        $this->project = $this->directory.'/project/deep/app';
        $this->cwd = $this->directory.'/launch/deep/current';
        mkdir($this->project.'/vendor', 0700, true);
        mkdir($this->cwd, 0700, true);
        copy(__DIR__.'/../run.php', $this->project.'/run.php');
        copy(__DIR__.'/fixtures/bootstrap-stubs.php', $this->project.'/vendor/autoload.php');
    }

    protected function tearDown(): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->directory);
    }

    public function testRealEnvironmentWinsWhenEnvSuperglobalIsEmpty(): void
    {
        $this->writeEnv($this->project);
        [$status, $output, $error] = $this->runBootstrap([
            'DISCORD_WEBHOOK' => 'os-webhook',
            'GITHUB_REPO_API' => 'os-repository',
            'LAST_COMMIT_FILE' => 'os-state.txt',
            'DOTENV_TEST_VALUE' => '0',
            'DOTENV_TEST_EMPTY' => '',
        ]);

        $this->assertSame(0, $status, $error);
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['os-webhook', 'os-repository', 'os-state.txt'], $result['configuration']);
        $this->assertSame('0', $result['value']);
        $this->assertSame('', $result['empty']);
    }

    public function testQuotesEqualsCommentsAndMalformedLines(): void
    {
        $this->writeEnv($this->project, <<<'ENV'

            # ignore this comment
        NOT_AN_ASSIGNMENT
        =ignore-empty-name
        bad name=ignore-invalid-name
        DISCORD_WEBHOOK="quoted webhook"
        GITHUB_REPO_API='quoted=repository'
        LAST_COMMIT_FILE="state with spaces.txt"
        DOTENV_TEST_VALUE="a=b # literal hash"
        DOTENV_TEST_EMPTY=''
        ENV);
        [$status, $output, $error] = $this->runBootstrap();

        $this->assertSame(0, $status, $error);
        $this->assertSame('', $error);
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['quoted webhook', 'quoted=repository', 'state with spaces.txt'], $result['configuration']);
        $this->assertSame('a=b # literal hash', $result['value']);
        $this->assertSame('', $result['empty']);
    }

    public function testExistingSuperglobalsAreNotOverwritten(): void
    {
        $this->writeEnv($this->project, "DOTENV_TEST_ENV=file\nDOTENV_TEST_SERVER=file\n");
        [$status, $output, $error] = $this->runBootstrap([], ['DOTENV_TEST_ENV' => 'existing'], [
            'DISCORD_WEBHOOK' => 'server-webhook',
            'GITHUB_REPO_API' => 'server-repository',
            'LAST_COMMIT_FILE' => 'server-state.txt',
            'DOTENV_TEST_SERVER' => '',
        ]);

        $this->assertSame(0, $status, $error);
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['server-webhook', 'server-repository', 'server-state.txt'], $result['configuration']);
        $this->assertFalse($result['env']);
        $this->assertFalse($result['server']);
    }

    public function testProjectFileWinsFromAnotherWorkingDirectory(): void
    {
        $this->writeEnv($this->project);
        file_put_contents($this->cwd.'/.env', "DISCORD_WEBHOOK=wrong-cwd\nGITHUB_REPO_API=wrong-cwd\n");
        [$status, $output, $error] = $this->runBootstrap();

        $this->assertSame(0, $status, $error);
        $this->assertSame(['fixture-webhook', 'fixture-repository', 'last_commit.txt'], json_decode($output, true)['configuration']);
    }

    public function testPackagedLaunchStillFindsWorkingDirectoryParent(): void
    {
        $this->writeEnv(dirname($this->cwd));
        [$status, $output, $error] = $this->runBootstrap();

        $this->assertSame(0, $status, $error);
        $this->assertSame(['fixture-webhook', 'fixture-repository', 'last_commit.txt'], json_decode($output, true)['configuration']);
    }

    public function testScriptParentIsFoundFromAnotherWorkingDirectory(): void
    {
        $this->writeEnv(dirname($this->project));
        [$status, $output, $error] = $this->runBootstrap();

        $this->assertSame(0, $status, $error);
        $this->assertSame(['fixture-webhook', 'fixture-repository', 'last_commit.txt'], json_decode($output, true)['configuration']);
    }

    public function testMissingFileStillFailsBeforeDownstreamExecution(): void
    {
        [$status, $output, $error] = $this->runBootstrap([
            'DISCORD_WEBHOOK' => 'os-webhook',
            'GITHUB_REPO_API' => 'os-repository',
        ]);

        $this->assertNotSame(0, $status);
        $this->assertStringContainsString('The .env file does not exist.', $output.$error);
        $this->assertFileDoesNotExist($this->project.'/last_commit.txt');
    }

    private function writeEnv(string $directory, string $extra = ''): void
    {
        file_put_contents($directory.'/.env', $extra."\nDISCORD_WEBHOOK=fixture-webhook\nGITHUB_REPO_API=fixture-repository\nDOTENV_TEST_VALUE=file\nDOTENV_TEST_EMPTY=file\n");
    }

    private function runBootstrap(array $environment = [], array $env = [], array $server = []): array
    {
        $runner = $this->project.'/test-runner.php';
        $emptyVariables = '';
        foreach ($environment as $name => $value) {
            if ($value === '') {
                // proc_open can omit empty values; set them inside the child.
                $emptyVariables .= 'putenv('.var_export($name.'=', true).');';
            }
        }
        file_put_contents($runner, '<?php '.$emptyVariables.' $_ENV = '.var_export($env, true).'; $_SERVER = '.var_export($server, true).'; require __DIR__."/run.php";');
        // Supply a fresh child environment; never inherit developer credentials.
        if (PHP_OS_FAMILY === 'Windows') {
            $environment['SystemRoot'] = getenv('SystemRoot');
        }
        $process = proc_open(
            [PHP_BINARY, '-d', 'allow_url_fopen=0', '-d', 'variables_order=GPCS', $runner],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->cwd,
            $environment
        );
        $this->assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output, $error];
    }
}
