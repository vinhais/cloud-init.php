<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use CloudInit\CloudInit;
use CloudInit\File;
use CloudInit\User;

// Replace this illustrative key with the contents of your own public key file.
$publicKey = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIExampleOnlyReplaceWithYourPublicKey deploy@example.com';

echo CloudInit::make()
    ->setHostname('web-01')
    ->setTimezone('UTC')
    ->setPackageUpdate()
    ->appendPackages(['nginx', 'curl'])
    ->appendDefaultUser()
    ->appendUser(User::make('deploy')->setShell('/bin/bash')->setGroups(['www-data'])->setSshKeys([$publicKey])->setLockPassword(true))
    ->appendFile(File::make('/var/www/html/index.html', "<h1>Server provisioned</h1>\n")->setOwner('www-data:www-data')->setPermissions('0644')->setDefer())
    ->appendRunCommand(['systemctl', 'enable', '--now', 'nginx'])
    ->renderString();
