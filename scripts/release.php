#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Reusable Composer package release script.
 *
 * It validates the package, runs quality gates, creates and pushes a Git tag,
 * and optionally notifies Packagist through its API.
 */
final class ReleaseScript
{
    /** @var list<string> */
    private array $argv;

    private bool $dryRun;

    /** @var list<string>|null */
    private ?array $composerCommand = null;

    /**
     * @param  list<string>  $argv
     */
    public function __construct(array $argv)
    {
        $this->argv = $argv;
        $this->dryRun = $this->hasFlag('dry-run');
    }

    public function run(): int
    {
        if ($this->hasFlag('help') || $this->hasFlag('h')) {
            $this->usage();

            return 0;
        }

        $root = dirname(__DIR__);
        chdir($root);

        $composer = $this->readComposer();
        $packageName = (string) ($composer['name'] ?? '');
        $version = $this->option('version');

        if ($version === '') {
            $this->fail('Missing required --version option.');
            $this->usage();

            return 2;
        }

        if (! preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z][0-9A-Za-z.-]*)?$/', $version)) {
            $this->fail('Version must be SemVer-like, for example 0.1.0 or 1.2.0-beta.1.');

            return 2;
        }

        if (array_key_exists('version', $composer)) {
            $this->fail('composer.json must not contain a version field. Composer versions come from VCS tags.');

            return 2;
        }

        $remote = $this->option('remote', $this->env('RELEASE_REMOTE', 'origin'));
        $branch = $this->option('branch', $this->env('RELEASE_BRANCH', 'main'));
        $tagPrefix = $this->option('tag-prefix', $this->env('RELEASE_TAG_PREFIX', 'v'));
        $tag = $tagPrefix.$version;
        $composerCommand = $this->composerCommand();
        $repository = $this->option(
            'packagist-repository',
            $this->env('PACKAGIST_REPOSITORY', $this->repositoryFromGitRemote($remote))
        );

        $this->line('Package: '.$packageName);
        $this->line('Version: '.$version);
        $this->line('Tag: '.$tag);
        $this->line('Remote: '.$remote);
        $this->line('Branch: '.$branch);
        $this->line('Composer: '.$this->shellCommand($composerCommand));

        if ($this->dryRun) {
            $this->skip('Dry run enabled. No tags, pushes or Packagist requests will be created.');
        }

        if (! $this->commandExists('git')) {
            $this->fail('Git is required.');

            return 1;
        }

        if (! $this->hasFlag('allow-dirty')) {
            $dirty = trim($this->capture(['git', 'status', '--porcelain']));

            if ($dirty !== '') {
                $this->fail('Working tree is not clean. Commit, stash or use --allow-dirty intentionally.');
                $this->line($dirty);

                return 1;
            }
        } else {
            $this->warn('Dirty working tree allowed by --allow-dirty.');
        }

        $currentBranch = trim($this->capture(['git', 'branch', '--show-current']));

        if ($branch !== '*' && $currentBranch !== $branch) {
            $this->fail(sprintf('Current branch is "%s"; expected "%s". Use --branch=* to bypass.', $currentBranch, $branch));

            return 1;
        }

        $pushBranch = $branch === '*' ? $currentBranch : $branch;

        if ($pushBranch === '') {
            $this->fail('Cannot detect a branch to push. Use --branch=NAME or --no-push.');

            return 1;
        }

        if (! $this->hasFlag('skip-tag') && $this->tagExists($tag, $remote)) {
            $this->fail(sprintf('Tag "%s" already exists locally or on remote "%s".', $tag, $remote));

            return 1;
        }

        $this->runQualityGates();

        if ($this->hasFlag('skip-tag')) {
            $this->skip('Tag creation skipped by --skip-tag.');
        } else {
            $this->runCommand(['git', 'tag', '-a', $tag, '-m', 'Release '.$tag]);
        }

        if ($this->hasFlag('no-push')) {
            $this->skip('Push skipped by --no-push.');
        } else {
            $this->runCommand(['git', 'push', $remote, $pushBranch]);

            if (! $this->hasFlag('skip-tag')) {
                $this->runCommand(['git', 'push', $remote, $tag]);
            }
        }

        $this->syncPackagist($repository);
        $this->ok('Release flow finished.');

        return 0;
    }

    private function runQualityGates(): void
    {
        $this->runCommand(array_merge($this->composerCommand(), ['validate', '--strict']));

        if ($this->hasFlag('skip-format')) {
            $this->skip('Format check skipped by --skip-format.');
        } else {
            $this->runCommand([$this->vendorBin('pint'), '--test']);
        }

        if ($this->hasFlag('skip-static')) {
            $this->skip('Static analysis skipped by --skip-static.');
        } else {
            $this->runCommand([$this->vendorBin('phpstan'), 'analyse', '--memory-limit=1G']);
        }

        if ($this->hasFlag('with-rector')) {
            $this->runCommand([$this->vendorBin('rector'), 'process', '--dry-run']);
        }

        if ($this->hasFlag('skip-tests')) {
            $this->skip('Tests skipped by --skip-tests.');
        } else {
            $this->runCommand([$this->vendorBin('pest')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readComposer(): array
    {
        $path = getcwd().DIRECTORY_SEPARATOR.'composer.json';

        if (! is_file($path)) {
            $this->fail('composer.json was not found in the current package root.');
            exit(1);
        }

        $json = file_get_contents($path);
        $decoded = json_decode((string) $json, true);

        if (! is_array($decoded)) {
            $this->fail('composer.json is not valid JSON.');
            exit(1);
        }

        return $decoded;
    }

    private function commandExists(string $command): bool
    {
        return $this->executablePath($command) !== '';
    }

    private function tagExists(string $tag, string $remote): bool
    {
        $localExit = 0;
        $null = $this->nullDevice();
        exec($this->shellCommand(['git', 'rev-parse', '--verify', '--quiet', 'refs/tags/'.$tag]).' >'.$null.' 2>'.$null, $unused, $localExit);

        if ($localExit === 0) {
            return true;
        }

        $remoteExit = 0;
        $remoteTags = [];
        exec($this->shellCommand(['git', 'ls-remote', '--tags', $remote, 'refs/tags/'.$tag]).' 2>'.$null, $remoteTags, $remoteExit);

        return $remoteExit === 0 && count($remoteTags) > 0;
    }

    /**
     * @param  list<string>  $command
     */
    private function runCommand(array $command): void
    {
        $display = $this->shellCommand($command);
        $this->line('$ '.$display);

        if ($this->dryRun) {
            return;
        }

        passthru($display, $exitCode);

        if ($exitCode !== 0) {
            $this->fail(sprintf('Command failed with exit code %d.', $exitCode));
            exit($exitCode);
        }
    }

    /**
     * @param  list<string>  $command
     */
    private function capture(array $command): string
    {
        $output = [];
        $exitCode = 0;
        exec($this->shellCommand($command).' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            $this->fail('Command failed: '.$this->shellCommand($command));
            $this->line(implode(PHP_EOL, $output));
            exit($exitCode);
        }

        return implode(PHP_EOL, $output);
    }

    /**
     * @param  list<string>  $command
     */
    private function shellCommand(array $command): string
    {
        return implode(' ', array_map('escapeshellarg', $command));
    }

    private function vendorBin(string $name): string
    {
        $path = 'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.$name;

        if (PHP_OS_FAMILY === 'Windows' && is_file($path.'.bat')) {
            return $path.'.bat';
        }

        return $path;
    }

    private function nullDevice(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    }

    private function repositoryFromGitRemote(string $remote): string
    {
        $url = trim($this->capture(['git', 'remote', 'get-url', $remote]));

        if (preg_match('/^git@([^:]+):(.+)$/', $url, $matches)) {
            return 'https://'.$matches[1].'/'.preg_replace('/\.git$/', '', $matches[2]).'.git';
        }

        return $url;
    }

    /**
     * @return list<string>
     */
    private function composerCommand(): array
    {
        if ($this->composerCommand !== null) {
            return $this->composerCommand;
        }

        $configured = $this->option('composer-bin', $this->env('RELEASE_COMPOSER_BIN'));

        if ($configured !== '') {
            return $this->composerCommand = $this->normalizeComposerCommand($configured);
        }

        $composer = $this->executablePath('composer');

        if ($composer === '') {
            $this->fail('Composer is required. Use --composer-bin=PATH or RELEASE_COMPOSER_BIN=PATH.');
            exit(1);
        }

        return $this->composerCommand = $this->normalizeComposerCommand($composer);
    }

    private function executablePath(string $command): string
    {
        $lines = [];
        $exitCode = 0;

        if (PHP_OS_FAMILY === 'Windows') {
            exec('where '.escapeshellarg($command).' 2>NUL', $lines, $exitCode);
        } else {
            exec('command -v '.escapeshellarg($command).' 2>/dev/null', $lines, $exitCode);
        }

        if ($exitCode !== 0) {
            return '';
        }

        foreach ($lines as $line) {
            $path = trim($line);

            if ($path !== '') {
                return $path;
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function normalizeComposerCommand(string $binary): array
    {
        $binary = trim($binary, " \t\n\r\0\x0B\"'");
        $lower = strtolower($binary);

        if (str_ends_with($lower, '.phar')) {
            return [PHP_BINARY, $binary];
        }

        if (preg_match('/\.(bat|cmd)$/', $lower) === 1) {
            $phar = dirname($binary).DIRECTORY_SEPARATOR.'composer.phar';

            if (is_file($phar)) {
                return [PHP_BINARY, $phar];
            }

            return ['cmd', '/d', '/c', $binary];
        }

        return [$binary];
    }

    private function syncPackagist(string $repository): void
    {
        if ($this->hasFlag('no-packagist')) {
            $this->skip('Packagist sync skipped by --no-packagist.');

            return;
        }

        if (($this->hasFlag('no-push') || $this->hasFlag('skip-tag')) && ! $this->hasFlag('force-packagist')) {
            $this->skip('Packagist sync skipped because tag or push was skipped. Use --force-packagist to override.');

            return;
        }

        $username = $this->env('PACKAGIST_USERNAME');
        $token = $this->env('PACKAGIST_TOKEN');

        if ($username === '' || $token === '') {
            $this->warn('PACKAGIST_USERNAME and PACKAGIST_TOKEN are not configured. Packagist sync skipped.');

            return;
        }

        if ($repository === '') {
            $this->fail('Packagist repository URL could not be detected. Use --packagist-repository=URL.');
            exit(1);
        }

        if ($this->hasFlag('create-packagist')) {
            $this->packagistRequest('create-package', $repository, $username, $token);
        }

        $this->packagistRequest('update-package', $repository, $username, $token);
    }

    private function packagistRequest(string $endpoint, string $repository, string $username, string $token): void
    {
        $url = 'https://packagist.org/api/'.$endpoint;
        $body = json_encode(['repository' => $repository], JSON_THROW_ON_ERROR);

        $this->line(sprintf('POST %s repository=%s', $url, $repository));

        if ($this->dryRun) {
            return;
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer '.$username.':'.$token,
            'User-Agent: ronu-php-library-release-script/1.0',
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers)."\r\n",
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 30,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? 'HTTP/1.1 000 Unknown';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        $status = (int) ($matches[1] ?? 0);

        if ($status < 200 || $status >= 300) {
            $this->fail(sprintf('Packagist %s failed with status %d.', $endpoint, $status));
            $this->line((string) $response);
            exit(1);
        }

        $this->ok('Packagist '.$endpoint.' accepted.');
    }

    private function option(string $name, string $default = ''): string
    {
        $prefix = '--'.$name.'=';

        foreach ($this->argv as $argument) {
            if (str_starts_with($argument, $prefix)) {
                return substr($argument, strlen($prefix));
            }
        }

        return $default;
    }

    private function hasFlag(string $name): bool
    {
        $flag = '--'.$name;

        foreach ($this->argv as $argument) {
            if ($argument === $flag) {
                return true;
            }
        }

        return false;
    }

    private function env(string $name, string $default = ''): string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? $default : $value;
    }

    private function usage(): void
    {
        $this->line(<<<'TEXT'
Usage:
  php scripts/release.php --version=0.1.0 [options]

Required:
  --version=VERSION              SemVer version to release. Example: 0.1.0

Git options:
  --branch=main                  Expected branch. Use --branch=* to bypass.
  --remote=origin                Git remote to push.
  --tag-prefix=v                 Tag prefix. Default creates v0.1.0.
  --allow-dirty                  Allow uncommitted changes.
  --skip-tag                     Do not create a local tag.
  --no-push                      Do not push branch or tag.

Quality options:
  --skip-format                  Skip Pint check.
  --skip-static                  Skip PHPStan.
  --skip-tests                   Skip Pest tests.
  --with-rector                  Run Rector in dry-run mode.
  --composer-bin=PATH            Composer executable, composer.bat or composer.phar.

Packagist options:
  --no-packagist                 Skip Packagist API sync.
  --create-packagist             Create the package on Packagist before update.
  --force-packagist              Sync Packagist even if push/tag was skipped.
  --packagist-repository=URL     Repository URL sent to Packagist.

Runtime:
  --dry-run                      Print commands and API calls without mutations.
  --help                         Show this help.

Environment:
  RELEASE_REMOTE                 Default remote.
  RELEASE_BRANCH                 Default branch.
  RELEASE_TAG_PREFIX             Default tag prefix.
  RELEASE_COMPOSER_BIN           Composer executable, composer.bat or composer.phar.
  PACKAGIST_USERNAME             Packagist username.
  PACKAGIST_TOKEN                Packagist API token.
  PACKAGIST_REPOSITORY           Repository URL sent to Packagist.
TEXT);
    }

    private function ok(string $message): void
    {
        $this->line('[ok] '.$message);
    }

    private function skip(string $message): void
    {
        $this->line('[skip] '.$message);
    }

    private function warn(string $message): void
    {
        $this->line('[warn] '.$message);
    }

    private function fail(string $message): void
    {
        $this->line('[fail] '.$message);
    }

    private function line(string $message): void
    {
        fwrite(STDOUT, $message.PHP_EOL);
    }
}

exit((new ReleaseScript($_SERVER['argv']))->run());
