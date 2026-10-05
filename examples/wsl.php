<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use CloudInit\Datasources\Wsl;
use CloudInit\MetaData;
use CloudInit\User;

// Without arguments, preview the per-instance documents as a named YAML stream.
// Supply an existing directory, optionally followed by the WSL instance name, to write files.
$directory = $argv[1] ?? null;
$instance = $argv[2] ?? 'Ubuntu-24.04';

$metadata = MetaData::make()->setInstanceId('iid-wsl-development');

try {
    $wsl = Wsl::make()
        ->setInstanceName($instance)
        ->setMetaData($metadata)
        ->setPackageUpdate()
        ->appendPackages(['git', 'curl'])
        ->appendDefaultUser()
        ->appendUser(User::make('developer')->setShell('/bin/bash')->setLockPassword(true));

    if ($directory === null) {
        echo "---\n# File: {$instance}.user-data\n".$wsl->renderString();
        echo "---\n# File: {$instance}.meta-data\n".$metadata->renderString();
    } else {
        foreach ($wsl->writeFiles($directory) as $path) {
            echo $path.PHP_EOL;
        }
    }
} catch (InvalidArgumentException | RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage()."\nUsage: php wsl.php [existing-output-directory] [instance-name]\n");
    exit(1);
}
