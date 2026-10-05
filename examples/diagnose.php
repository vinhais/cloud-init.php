<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use CloudInit\Concerns\RendersYaml;
use CloudInit\File;
use CloudInit\User;
use CloudInit\UserData;
use Composer\InstalledVersions;
use Symfony\Component\Yaml\Yaml;

// Report the code and dependency actually loaded by this PHP CLI process.
$renderer = new ReflectionClass(RendersYaml::class);
$yamlClass = new ReflectionClass(Yaml::class);
$config = UserData::make()
    ->appendUser(User::make('deploy'))
    ->appendFile(File::make('/etc/example.conf', "example\n"))
    ->appendRunCommand(['systemctl', 'enable', '--now', 'nginx']);
$output = $config->renderString();
$compact = str_contains($output, "users:\n  - name: deploy\n")
    && str_contains($output, "write_files:\n  - path: /etc/example.conf\n");

echo json_encode([
    'php_version' => PHP_VERSION,
    'php_binary' => PHP_BINARY,
    'symfony_yaml_version' => InstalledVersions::getPrettyVersion('symfony/yaml'),
    'symfony_yaml_file' => $yamlClass->getFileName(),
    'renderer_file' => $renderer->getFileName(),
    'compact_mapping_flag_available' => defined(Yaml::class.'::DUMP_COMPACT_NESTED_MAPPING'),
    'compact_mapping_output' => $compact,
    'opcache_enable_cli' => ini_get('opcache.enable_cli'),
    'opcache_validate_timestamps' => ini_get('opcache.validate_timestamps'),
    'rendered_yaml' => $output,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;

exit($compact ? 0 : 1);
