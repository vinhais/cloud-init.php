<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use CloudInit\MetaData;
use CloudInit\Network\Ethernet;
use CloudInit\Network\Route;
use CloudInit\NetworkConfig;
use CloudInit\UserData;

// Without arguments, preview all three documents as a named YAML stream.
// Supply an existing directory to write the separate NoCloud files instead.
$directory = $argv[1] ?? null;

$userData = UserData::make()
    ->setHostname('web-01')
    ->setPackageUpdate()
    ->appendPackages(['nginx']);

$metaData = MetaData::make()
    ->setInstanceId('iid-web-01')
    ->setLocalHostname('web-01');

$network = NetworkConfig::make()->setEthernet(
    Ethernet::make('eth0')
        ->setDhcp4(false)
        ->setAddresses(['192.0.2.10/24'])
        ->setNameservers(['192.0.2.53'])
        ->setSearchDomains(['example.com'])
        ->appendRoute(Route::make('default', '192.0.2.1'))
);

$documents = ['user-data' => $userData, 'meta-data' => $metaData, 'network-config' => $network];

try {
    foreach ($documents as $filename => $document) {
        if ($directory === null) {
            echo "---\n# File: {$filename}\n".$document->renderString();

            continue;
        }

        echo $document->renderYaml($directory, $filename).PHP_EOL;
    }
} catch (InvalidArgumentException | RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage()."\nUsage: php nocloud.php [existing-output-directory]\n");
    exit(1);
}
