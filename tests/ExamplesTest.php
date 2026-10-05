<?php

declare(strict_types=1);

namespace CloudInit\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Run the actual CLI entry points from the examples directory.
 */
final class ExamplesTest extends TestCase
{
    /**
     * Supply every user-facing example that must work without arguments.
     *
     * @return iterable<string, array{string, int}>
     */
    public static function previews(): iterable
    {
        yield 'web server' => ['web-server.php', 1];
        yield 'LXD' => ['lxd.php', 1];
        yield 'NoCloud' => ['nocloud.php', 3];
        yield 'MAAS' => ['maas.php', 1];
        yield 'WSL' => ['wsl.php', 2];
    }

    /**
     * Preview invocations succeed and produce readable, parseable documents.
     *
     * @param  string  $script  Example filename.
     * @param  int  $count  Expected number of YAML documents.
     * @return void
     */
    #[DataProvider('previews')]
    public function testPreview(string $script, int $count): void
    {
        [$status, $output, $errors] = $this->runExample([$script]);
        self::assertSame(0, $status, $errors);
        self::assertSame('', $errors);
        $documents = $count === 1 ? [$output] : array_slice(explode("---\n", $output), 1);
        self::assertCount($count, $documents);

        foreach ($documents as $document) {
            self::assertIsArray(Yaml::parse($document));
        }

        if ($script === 'web-server.php') {
            self::assertStringContainsString("  - name: deploy\n", $output, 'Run php examples/diagnose.php to inspect the loaded renderer.');
            self::assertStringContainsString("  - path: /var/www/html/index.html\n", $output);
            self::assertStringContainsString("  - [systemctl, enable, '--now', nginx]\n", $output);
        }
    }

    /**
     * Base64 transport is explicit and decodes to the default MAAS preview.
     *
     * @return void
     */
    public function testMaasTransport(): void
    {
        [$status, $encoded, $errors] = $this->runExample(['maas.php', '--base64']);
        [, $yaml] = $this->runExample(['maas.php']);
        self::assertSame(0, $status, $errors);
        self::assertSame($yaml, base64_decode(trim($encoded), true));
    }

    /**
     * A supplied directory produces named files and reports their paths.
     *
     * @return void
     */
    public function testFileOutput(): void
    {
        $directory = sys_get_temp_dir().'/cloud-init-examples-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $files = ['user-data', 'meta-data', 'network-config', 'Ubuntu-24.04.user-data', 'Ubuntu-24.04.meta-data'];

        try {
            foreach (['nocloud.php', 'wsl.php'] as $script) {
                [$status, $output, $errors] = $this->runExample([$script, $directory]);
                self::assertSame(0, $status, $errors);
                self::assertSame('', $errors);
                self::assertStringContainsString($directory.'/', $output);
            }

            foreach ($files as $filename) {
                self::assertFileExists($directory.'/'.$filename);
                self::assertIsArray(Yaml::parseFile($directory.'/'.$filename));
            }
        } finally {
            foreach ($files as $filename) {
                if (is_file($directory.'/'.$filename)) {
                    unlink($directory.'/'.$filename);
                }
            }

            rmdir($directory);
        }
    }

    /**
     * Invalid CLI destinations produce actionable errors without uncaught traces.
     *
     * @return void
     */
    public function testInvalidDestination(): void
    {
        $missing = sys_get_temp_dir().'/missing-'.bin2hex(random_bytes(8));

        foreach (['nocloud.php', 'wsl.php'] as $script) {
            [$status, $output, $errors] = $this->runExample([$script, $missing]);
            self::assertSame(1, $status);
            self::assertSame('', $output);
            self::assertStringContainsString('Usage: php '.$script, $errors);
            self::assertStringNotContainsString('Fatal error', $errors);
            self::assertStringNotContainsString('Stack trace', $errors);
        }
    }

    /**
     * Diagnostics report the source actually loaded and test compact rendering.
     *
     * @return void
     */
    public function testRenderingDiagnostics(): void
    {
        [$status, $output, $errors] = $this->runExample(['diagnose.php']);
        self::assertSame(0, $status, $errors);
        $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($report['compact_mapping_output']);
        self::assertSame(realpath(dirname(__DIR__).'/src/Concerns/RendersYaml.php'), $report['renderer_file']);
    }

    /**
     * Execute the PHP CLI with the same working directory used in manual runs.
     *
     * @param  non-empty-list<string>  $arguments  Script and optional CLI arguments.
     * @return array{int, string, string} Exit status, stdout and stderr.
     */
    private function runExample(array $arguments): array
    {
        $process = proc_open(
            [PHP_BINARY, ...$arguments],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__).'/examples',
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertIsString($stdout);
        self::assertIsString($stderr);

        return [proc_close($process), $stdout, $stderr];
    }
}
