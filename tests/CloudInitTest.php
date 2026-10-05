<?php

declare(strict_types=1);

namespace CloudInit\Tests;

use CloudInit\CloudInit;
use CloudInit\File;
use CloudInit\User;
use CloudInit\Exceptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Verify fluent semantics and the actual YAML representation consumed by cloud-init.
 */
final class CloudInitTest extends TestCase
{
    /**
     * The entry point must emit a cloud-config document, including when empty.
     *
     * @return void
     */
    public function testEmptyDocument(): void
    {
        self::assertSame("#cloud-config\n{}\n", CloudInit::make()->render());
    }

    /**
     * String rendering has no output side effects and retains the original alias.
     *
     * @return void
     */
    public function testRenderString(): void
    {
        $this->expectOutputString('');
        $config = CloudInit::make()->setHostname('teste');
        self::assertSame("#cloud-config\nhostname: teste\n", $config->renderString());
        self::assertSame($config->render(), $config->renderString());
    }

    /**
     * File rendering creates and replaces the named file with exactly the rendered content.
     *
     * @return void
     */
    public function testRenderYaml(): void
    {
        $directory = sys_get_temp_dir().'/cloud-init-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $path = $directory.'/user-data.yaml';
        try {
            $config = CloudInit::make()->setHostname('long-original-hostname');
            self::assertSame($path, $config->renderYaml($directory.'/', 'user-data.yaml'));
            self::assertSame($config->renderString(), file_get_contents($path));
            $config->setHostname('new')->renderYaml($directory, 'user-data.yaml');
            self::assertSame($config->renderString(), file_get_contents($path));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }

            rmdir($directory);
        }
    }

    /**
     * An unwritable target raises an exception instead of reporting success.
     *
     * @return void
     */
    public function testRenderYamlWriteFailure(): void
    {
        $directory = sys_get_temp_dir().'/cloud-init-'.bin2hex(random_bytes(8));
        mkdir($directory);
        mkdir($directory.'/occupied.yaml');
        try {
            $this->expectException(Exceptions\FileWriteException::class);
            CloudInit::make()->renderYaml($directory, 'occupied.yaml');
        } finally {
            rmdir($directory.'/occupied.yaml');
            rmdir($directory);
        }
    }

    /**
     * YAML-sensitive strings, multiline content and false values survive serialization.
     *
     * @return void
     */
    public function testCompleteDocumentRoundTrip(): void
    {
        $config = CloudInit::make()
            ->setHostname('teste')
            ->setFqdn('teste.example.com')
            ->setTimezone('America/Sao_Paulo')
            ->setLocale('pt_BR.UTF-8')
            ->setPreserveHostname(false)
            ->setPackageUpdate()
            ->setPackageUpgrade(false)
            ->setDisableRoot()
            ->setSshPasswordAuthentication(false)
            ->appendSshKeys(['ssh-ed25519 AAAA test@example.com'])
            ->appendPackages(['nginx', 'on'])
            ->appendDefaultUser()
            ->appendUser(User::make('deploy')->setGroups(['www-data'])->setShell('/bin/bash')->setLockPassword(true))
            ->appendFile(File::make('/etc/example.conf', "enabled: true\nvalue: 'yes'\n")->setPermissions('0640')->setOwner('root:root')->setDefer()->setAppend(false))
            ->appendRunCommand(['printf', '%s', '', 'a: b # c'])
            ->appendRunCommand('echo "hello: world"')
            ->appendBootCommand(['mkdir', '-p', '/run/example']);

        self::assertStringStartsWith("#cloud-config\n", $config->render());
        self::assertStringContainsString("users:\n  - default\n  - name: deploy\n", $config->render());
        self::assertStringContainsString("write_files:\n  - path: /etc/example.conf\n", $config->render());
        self::assertSame($config->toArray(), Yaml::parse($config->render()));
        self::assertSame($config->render(), (string) $config);
        self::assertSame('0640', Yaml::parse($config->render())['write_files'][0]['permissions']);
        self::assertSame(false, $config->toArray()['ssh_pwauth']);
    }

    /**
     * Keep command argument lists on one line without changing shell semantics.
     *
     * @return void
     */
    public function testInlineCommandRendering(): void
    {
        $config = CloudInit::make()
            ->appendRunCommand(['systemctl', 'enable', '--now', 'nginx'])
            ->appendRunCommand(['printf', '%s', '', 'a: b # c', '$HOME', "line one\nline two"])
            ->appendRunCommand('echo "$HOME" && true')
            ->appendBootCommand(['mkdir', '-p', '/run/example'])
            ->appendFile(File::make('/etc/example.conf', "enabled: true\n"));
        $yaml = $config->renderString();

        self::assertStringContainsString("runcmd:\n  - [systemctl, enable, '--now', nginx]\n", $yaml);
        self::assertStringContainsString("bootcmd:\n  - [mkdir, '-p', /run/example]\n", $yaml);
        self::assertStringContainsString("content: |\n", $yaml);
        self::assertSame($config->toArray(), Yaml::parse($yaml));
    }

    /**
     * Set replaces, append accumulates, and builders do not share state.
     *
     * @return void
     */
    public function testListsAndIsolation(): void
    {
        $config = CloudInit::make()->appendSshKeys(['old'])->setSshKeys(['one'])->appendSshKeys(['two', 'one'])
            ->appendPackages(['old'])->setPackages(['php'])->appendPackages(['nginx']);
        self::assertSame(['one', 'two', 'one'], $config->toArray()['ssh_authorized_keys']);
        self::assertSame(['php', 'nginx'], $config->toArray()['packages']);
        self::assertSame([], CloudInit::make()->toArray());
        self::assertSame([], $config->setSshKeys([])->toArray()['ssh_authorized_keys']);
    }

    /**
     * Invalid batch input must not partially modify existing state.
     *
     * @return void
     */
    public function testAppendIsAtomic(): void
    {
        $config = CloudInit::make()->appendSshKeys(['original']);
        try {
            $config->appendSshKeys(['valid', 42]);
            self::fail('Invalid key accepted.');
        } catch (Exceptions\SshKeyInvalidException) {
            self::assertSame(['original'], $config->toArray()['ssh_authorized_keys']);
        }
    }

    /**
     * Conditional operations preserve fluent identity and select the expected branch.
     *
     * @return void
     */
    public function testConditions(): void
    {
        $config = CloudInit::make();
        $result = $config->when(true, fn (CloudInit $c) => $c->setHostname('yes'))
            ->when(false, fn () => self::fail('Unexpected callback'), fn (CloudInit $c) => $c->setLocale('en_US.UTF-8'))
            ->unless(false, fn (CloudInit $c) => $c->setPackageUpdate())
            ->unless(true, fn () => self::fail('Unexpected callback'))
            ->tap(fn (CloudInit $c) => self::assertSame('yes', $c->toArray()['hostname']));
        self::assertSame($config, $result);
        self::assertSame(['hostname' => 'yes', 'locale' => 'en_US.UTF-8', 'package_update' => true], $config->toArray());
    }

    /**
     * Appended value objects and array snapshots cannot mutate the builder indirectly.
     *
     * @return void
     */
    public function testSnapshots(): void
    {
        $user = User::make('deploy')->setShell('/bin/bash');
        $file = File::make('/etc/app.conf', 'original');
        $config = CloudInit::make()->appendUser($user)->appendFile($file);
        $user->setShell('/bin/sh');
        $file->setContent('changed');
        $snapshot = $config->toArray();
        $snapshot['users'][0]['shell'] = 'changed';
        self::assertSame('/bin/bash', $config->toArray()['users'][0]['shell']);
        self::assertSame('original', $config->toArray()['write_files'][0]['content']);
    }

    /**
     * Provide invalid inputs and the expected validation exception for each case.
     *
     * @return iterable<string, array{class-string<Exceptions\ValidationException>, \Closure(): mixed}> Invalid inputs across public entry points.
     */
    public static function invalidInputs(): iterable
    {
        yield 'fqdn' => [Exceptions\FqdnInvalidException::class, fn () => CloudInit::make()->setFqdn(' ')];
        yield 'locale' => [Exceptions\LocaleInvalidException::class, fn () => CloudInit::make()->setLocale(' ')];
        yield 'blank timezone' => [Exceptions\TimezoneInvalidException::class, fn () => CloudInit::make()->setTimezone(' ')];
        yield 'shell' => [Exceptions\ShellInvalidException::class, fn () => User::make('deploy')->setShell(' ')];
        yield 'home' => [Exceptions\HomeDirectoryInvalidException::class, fn () => User::make('deploy')->setHomeDirectory(' ')];
        yield 'gecos' => [Exceptions\GecosInvalidException::class, fn () => User::make('deploy')->setGecos(' ')];
        yield 'primary group' => [Exceptions\GroupInvalidException::class, fn () => User::make('deploy')->setPrimaryGroup(' ')];
        yield 'sudo' => [Exceptions\SudoRuleInvalidException::class, fn () => User::make('deploy')->setSudo(' ')];
        yield 'user keys' => [Exceptions\SshKeyInvalidException::class, fn () => User::make('deploy')->setSshKeys([''])];
        yield 'append keys' => [Exceptions\SshKeyInvalidException::class, fn () => CloudInit::make()->appendSshKeys([''])];
        yield 'set packages' => [Exceptions\PackageInvalidException::class, fn () => CloudInit::make()->setPackages([''])];
        yield 'blank directory' => [Exceptions\DirectoryInvalidException::class, fn () => CloudInit::make()->renderYaml('', 'a.yaml')];
        yield 'missing directory' => [Exceptions\DirectoryInvalidException::class, fn () => CloudInit::make()->renderYaml(sys_get_temp_dir().'/missing-'.bin2hex(random_bytes(8)), 'a.yaml')];
        yield 'blank filename' => [Exceptions\FilenameInvalidException::class, fn () => CloudInit::make()->renderYaml(sys_get_temp_dir(), ' ')];
        yield 'parent filename' => [Exceptions\FilenameInvalidException::class, fn () => CloudInit::make()->renderYaml(sys_get_temp_dir(), '..')];
        yield 'traversal filename' => [Exceptions\FilenameInvalidException::class, fn () => CloudInit::make()->renderYaml(sys_get_temp_dir(), '../a.yaml')];
        yield 'backslash filename' => [Exceptions\FilenameInvalidException::class, fn () => CloudInit::make()->renderYaml(sys_get_temp_dir(), 'a\\b.yaml')];
        yield 'null filename' => [Exceptions\FilenameInvalidException::class, fn () => CloudInit::make()->renderYaml(sys_get_temp_dir(), "a\0.yaml")];
        yield 'null directory' => [Exceptions\DirectoryInvalidException::class, fn () => CloudInit::make()->renderYaml("/tmp\0", 'a.yaml')];
        yield 'stream directory' => [Exceptions\DirectoryInvalidException::class, fn () => CloudInit::make()->renderYaml('file:///tmp', 'a.yaml')];
        yield 'blank hostname' => [Exceptions\HostnameInvalidException::class, fn () => CloudInit::make()->setHostname(' ')];
        yield 'timezone' => [Exceptions\TimezoneInvalidException::class, fn () => CloudInit::make()->setTimezone('Invalid/Zone')];
        yield 'associative keys' => [Exceptions\SshKeyInvalidException::class, fn () => CloudInit::make()->setSshKeys(['key' => 'ssh'])];
        yield 'invalid package' => [Exceptions\PackageInvalidException::class, fn () => CloudInit::make()->appendPackages([false])];
        yield 'empty command' => [Exceptions\CommandInvalidException::class, fn () => CloudInit::make()->appendRunCommand([])];
        yield 'blank command' => [Exceptions\CommandInvalidException::class, fn () => CloudInit::make()->appendRunCommand(' ')];
        yield 'invalid argument' => [Exceptions\CommandInvalidException::class, fn () => CloudInit::make()->appendBootCommand(['echo', 1])];
        yield 'blank executable' => [Exceptions\CommandInvalidException::class, fn () => CloudInit::make()->appendRunCommand(['', 'hello'])];
        yield 'relative file' => [Exceptions\FilePathInvalidException::class, fn () => File::make('relative')];
        yield 'null path' => [Exceptions\FilePathInvalidException::class, fn () => File::make("/etc/a\0b")];
        yield 'permissions' => [Exceptions\FilePermissionsInvalidException::class, fn () => File::make('/etc/a')->setPermissions('0899')];
        yield 'owner' => [Exceptions\FileOwnerInvalidException::class, fn () => File::make('/etc/a')->setOwner('root')];
        yield 'user' => [Exceptions\UserNameInvalidException::class, fn () => User::make('')];
        yield 'groups' => [Exceptions\GroupInvalidException::class, fn () => User::make('deploy')->setGroups([''])];
    }

    /**
     * Every malformed input yields its specific validation exception.
     *
     * @param  class-string<Exceptions\ValidationException>  $exception  Validation exception class to throw for invalid input.
     * @param  \Closure  $operation
     * @return void
     */
    #[DataProvider('invalidInputs')]
    public function testInvalidInput(string $exception, \Closure $operation): void
    {
        $this->expectException($exception);
        self::assertTrue(is_subclass_of($exception, Exceptions\ValidationException::class));
        self::assertTrue(is_subclass_of($exception, \InvalidArgumentException::class));
        $operation();
    }
}
