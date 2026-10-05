<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use CloudInit\Datasources\Maas;

$maas = Maas::make()->setHostname('maas-web')->setPackageUpdate()->appendPackages(['nginx']);

// Preview readable cloud-config by default; request the MAAS transport value explicitly.
if (($argv[1] ?? null) === '--base64') {
    echo $maas->toDeployParameters()['user_data'].PHP_EOL;
} elseif (isset($argv[1])) {
    fwrite(STDERR, "Usage: php maas.php [--base64]\n");
    exit(1);
} else {
    echo $maas->renderString();
}
