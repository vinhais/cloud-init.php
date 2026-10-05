<?php

declare(strict_types=1);

namespace CloudInit\Tests;

use CloudInit\CloudInit;
use CloudInit\Datasources\Lxd;
use CloudInit\Datasources\Maas;
use CloudInit\Datasources\Wsl;
use CloudInit\Exceptions;
use CloudInit\MetaData;
use CloudInit\Network\Ethernet;
use CloudInit\NetworkConfig;
use CloudInit\UserData;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Verify platform payloads without contacting hypervisors or deployment services.
 */
final class DatasourcesTest extends TestCase
{
    /**
     * LXD embeds strings that independently parse as user, vendor and network data.
     *
     * @return void
     */
    public function testLxdPayload(): void
    {
        $user = CloudInit::make()->setHostname('lxd-web');
        $vendor = UserData::make()->setPackageUpdate();
        $network = NetworkConfig::make()->setEthernet(Ethernet::make('eth0')->setDhcp4());
        $lxd = Lxd::make()->setUserData($user)->setVendorData($vendor)->setNetworkConfig($network);
        $payload = Yaml::parse($lxd->renderString());

        self::assertSame(['config' => [
            'cloud-init.user-data' => $user->renderString(),
            'cloud-init.vendor-data' => $vendor->renderString(),
            'cloud-init.network-config' => $network->renderString(),
        ]], $payload);
        self::assertSame(['hostname' => 'lxd-web'], Yaml::parse($payload['config']['cloud-init.user-data']));
        self::assertSame($network->toArray(), Yaml::parse($payload['config']['cloud-init.network-config']));
        self::assertStringStartsWith("config:\n", $lxd->renderString());
        self::assertStringContainsString("cloud-init.user-data: |\n", $lxd->renderString());
        self::assertSame($lxd->renderString(), (string) $lxd);
    }

    /**
     * Legacy keys switch without duplicating values or depending on setter order.
     *
     * @return void
     */
    public function testLxdLegacyKeysAndSnapshots(): void
    {
        $user = UserData::make()->setHostname('original');
        $lxd = Lxd::make()->setUserData($user)->useLegacyKeys();
        $user->setHostname('changed');

        self::assertSame(['user.user-data' => "#cloud-config\nhostname: original\n"], $lxd->toArray()['config']);
        self::assertArrayHasKey('cloud-init.user-data', $lxd->useLegacyKeys(false)->toArray()['config']);
        $lxd->setUserData(UserData::make());
        self::assertSame(['cloud-init.user-data' => "#cloud-config\n{}\n"], $lxd->toArray()['config']);
    }

    /**
     * MAAS receives one base64 encoding of the complete cloud-config document.
     *
     * @return void
     */
    public function testMaasDeployParameters(): void
    {
        $maas = Maas::make()->setHostname('maas-web')->appendPackages(['nginx']);
        $parameters = $maas->toDeployParameters();

        self::assertInstanceOf(Maas::class, $maas);
        self::assertSame(['user_data'], array_keys($parameters));
        self::assertSame($maas->renderString(), base64_decode($parameters['user_data'], true));
        self::assertSame($maas->toArray(), Yaml::parse(base64_decode($parameters['user_data'], true)));
        self::assertStringStartsWith("#cloud-config\n", $maas->renderString());
    }

    /**
     * WSL writes separate per-instance files and snapshots optional metadata.
     *
     * @return void
     */
    public function testWslFiles(): void
    {
        $directory = sys_get_temp_dir().'/cloud-init-wsl-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $metadata = MetaData::make()->setInstanceId('iid-original');
        $wsl = Wsl::make()->setInstanceName('Ubuntu-24.04')->appendPackages(['git'])->setMetaData($metadata);
        $metadata->setInstanceId('iid-changed');

        try {
            $paths = $wsl->writeFiles($directory);
            self::assertSame([
                'user-data' => $directory.'/Ubuntu-24.04.user-data',
                'meta-data' => $directory.'/Ubuntu-24.04.meta-data',
            ], $paths);
            self::assertSame($wsl->renderString(), file_get_contents($paths['user-data']));
            self::assertSame(['instance-id' => 'iid-original'], Yaml::parseFile($paths['meta-data']));
            self::assertArrayNotHasKey('instanceName', $wsl->toArray());
            self::assertArrayNotHasKey('meta-data', $wsl->toArray());
            self::assertInstanceOf(Wsl::class, $wsl);
        } finally {
            foreach (['Ubuntu-24.04.user-data', 'Ubuntu-24.04.meta-data'] as $filename) {
                if (is_file($directory.'/'.$filename)) {
                    unlink($directory.'/'.$filename);
                }
            }

            rmdir($directory);
        }
    }

    /**
     * Generic file rendering remains available for every adapter.
     *
     * @return void
     */
    public function testAdapterRenderingAndOptionalMetadata(): void
    {
        $directory = sys_get_temp_dir().'/cloud-init-adapters-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $lxd = Lxd::make()->setUserData(UserData::make()->appendPackages(['curl']));
        $maas = Maas::make()->setHostname('maas-node');
        $wsl = Wsl::make()->setInstanceName('Development');

        try {
            foreach (['lxd.yaml' => $lxd, 'maas.yaml' => $maas] as $filename => $builder) {
                $path = $builder->renderYaml($directory, $filename);
                self::assertSame($builder->renderString(), file_get_contents($path));
            }

            self::assertSame(['user-data' => $directory.'/Development.user-data'], $wsl->writeFiles($directory));
            self::assertFileDoesNotExist($directory.'/Development.meta-data');
        } finally {
            foreach (['lxd.yaml', 'maas.yaml', 'Development.user-data'] as $filename) {
                if (is_file($directory.'/'.$filename)) {
                    unlink($directory.'/'.$filename);
                }
            }

            rmdir($directory);
        }
    }

    /**
     * Supply invalid adapter input and the domain-specific error it must raise.
     *
     * @return iterable<string, array{class-string<\CloudInit\Exceptions\ValidationException>, Closure(): mixed}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'empty LXD' => [Exceptions\LxdConfigInvalidException::class, fn () => Lxd::make()->renderString()];
        yield 'invalid network' => [Exceptions\NetworkConfigInvalidException::class, fn () => Lxd::make()->setNetworkConfig(NetworkConfig::make())];
        yield 'missing WSL name' => [Exceptions\WslInstanceNameInvalidException::class, fn () => Wsl::make()->writeFiles(sys_get_temp_dir())];
        yield 'blank WSL name' => [Exceptions\WslInstanceNameInvalidException::class, fn () => Wsl::make()->setInstanceName(' ')];
        yield 'traversal' => [Exceptions\WslInstanceNameInvalidException::class, fn () => Wsl::make()->setInstanceName('../Ubuntu')];
        yield 'Windows separator' => [Exceptions\WslInstanceNameInvalidException::class, fn () => Wsl::make()->setInstanceName('..\\Ubuntu')];
        yield 'null byte' => [Exceptions\WslInstanceNameInvalidException::class, fn () => Wsl::make()->setInstanceName("Ubuntu\0")];
        yield 'colon' => [Exceptions\WslInstanceNameInvalidException::class, fn () => Wsl::make()->setInstanceName('C:Ubuntu')];
        yield 'trailing dot' => [Exceptions\WslInstanceNameInvalidException::class, fn () => Wsl::make()->setInstanceName('Ubuntu.')];
        yield 'trailing space' => [Exceptions\WslInstanceNameInvalidException::class, fn () => Wsl::make()->setInstanceName('Ubuntu ')];
        yield 'reserved name' => [Exceptions\WslInstanceNameInvalidException::class, fn () => Wsl::make()->setInstanceName('CON')];
        yield 'invalid metadata' => [Exceptions\InstanceIdInvalidException::class, fn () => Wsl::make()->setMetaData(MetaData::make())];
    }

    /**
     * Ensure invalid inputs fail with a catchable validation exception.
     *
     * @param  class-string<\CloudInit\Exceptions\ValidationException>  $exception  Expected exception type.
     * @param  \Closure(): mixed  $operation  Invalid adapter operation.
     * @return void
     */
    #[DataProvider('invalidInputs')]
    public function testInvalidInput(string $exception, Closure $operation): void
    {
        $this->expectException($exception);
        $operation();
    }
}
