<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use CloudInit\Datasources\Lxd;
use CloudInit\Network\Ethernet;
use CloudInit\NetworkConfig;
use CloudInit\UserData;

// Print a config fragment to merge into an LXD instance or profile.
echo Lxd::make()
    ->setUserData(UserData::make()->setHostname('lxd-web')->appendPackages(['nginx']))
    ->setVendorData(UserData::make()->setPackageUpdate())
    ->setNetworkConfig(NetworkConfig::make()->setEthernet(Ethernet::make('eth0')->setDhcp4()))
    ->renderString();
