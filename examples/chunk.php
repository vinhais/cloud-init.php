<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use CloudInit\CloudInit;
use CloudInit\MetaData;
use CloudInit\Network\Ethernet;
use CloudInit\NetworkConfig;

$userData = CloudInit::make()->setHostname('web-01')->appendPackages(['nginx']);
$metaData = MetaData::make()->setInstanceId('iid-web-01')->setLocalHostname('web-01');
$networkConfig = NetworkConfig::make()->setEthernet(Ethernet::make('eth0')->setDhcp4());

// Package all documents in one YAML file for application transport.
// Extract the named strings before providing the documents to NoCloud.
echo $userData->chunk($metaData, $networkConfig)->renderString();
