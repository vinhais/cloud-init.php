<?php

declare(strict_types=1);

namespace CloudInit\Tests;

use CloudInit\CloudInit;
use CloudInit\Exceptions;
use CloudInit\MetaData;
use CloudInit\Network\Ethernet;
use CloudInit\Network\Route;
use CloudInit\NetworkConfig;
use CloudInit\UserData;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Verify independent NoCloud documents, typed networking and filesystem output.
 */
final class DocumentsTest extends TestCase
{
    /**
     * Existing calls retain their concrete type and cloud-config output.
     *
     * @return void
     */
    public function testUserDataCompatibility(): void
    {
        $legacy = CloudInit::make()->setHostname('web-01');
        $explicit = UserData::make()->setHostname('web-01');

        self::assertInstanceOf(CloudInit::class, $legacy);
        self::assertInstanceOf(UserData::class, $legacy);
        self::assertSame("#cloud-config\nhostname: web-01\n", $explicit->renderString());
        self::assertSame($legacy->renderString(), $explicit->renderString());
    }

    /**
     * Metadata emits hyphenated keys without a user-data header.
     *
     * @return void
     */
    public function testMetadata(): void
    {
        $metadata = MetaData::make()->setInstanceId('iid-01')->setLocalHostname('web-01');

        self::assertSame("instance-id: iid-01\nlocal-hostname: web-01\n", $metadata->renderString());
        self::assertSame($metadata->toArray(), Yaml::parse($metadata->renderString()));
        self::assertSame($metadata->renderString(), (string) $metadata);
        self::assertSame($metadata->renderString(), $metadata->render());
        self::assertSame(['instance-id' => 'iid-02'], MetaData::make()->setInstanceId('iid-02')->toArray());
    }

    /**
     * Version 2 is top-level in NoCloud network-config and preserves false values.
     *
     * @return void
     */
    public function testDhcp(): void
    {
        $network = NetworkConfig::make()->setEthernet(Ethernet::make('eth0')->setDhcp4()->setDhcp6(false));
        $expected = ['version' => 2, 'ethernets' => ['eth0' => ['dhcp4' => true, 'dhcp6' => false]]];

        self::assertSame($expected, Yaml::parse($network->renderString()));
        self::assertStringStartsWith("version: 2\n", $network->renderString());
        self::assertSame($network->renderString(), (string) $network);
    }

    /**
     * Static dual-stack addresses, DNS, matching and routes use canonical keys.
     *
     * @return void
     */
    public function testStaticNetwork(): void
    {
        $ethernet = Ethernet::make('uplink')
            ->setMatchMacAddress('52:54:00:12:34:56')->setName('eth0')
            ->setDhcp4(false)->setDhcp6(false)->setMtu(1500)
            ->setAddresses(['192.0.2.10/24', '2001:db8::10/64'])
            ->setSearchDomains(['example.com'])->setNameservers(['192.0.2.53', '2001:db8::53'])
            ->appendRoute(Route::make('default', '192.0.2.1')->setMetric(0))
            ->appendRoute(Route::make('::/0', '2001:db8::1')->setMetric(100));
        $actual = Yaml::parse(NetworkConfig::make()->setEthernet($ethernet)->renderString());

        self::assertSame([
            'version' => 2,
            'ethernets' => ['uplink' => [
                'match' => ['macaddress' => '52:54:00:12:34:56'],
                'set-name' => 'eth0', 'dhcp4' => false, 'dhcp6' => false, 'mtu' => 1500,
                'addresses' => ['192.0.2.10/24', '2001:db8::10/64'],
                'nameservers' => ['search' => ['example.com'], 'addresses' => ['192.0.2.53', '2001:db8::53']],
                'routes' => [
                    ['to' => 'default', 'via' => '192.0.2.1', 'metric' => 0],
                    ['to' => '::/0', 'via' => '2001:db8::1', 'metric' => 100],
                ],
            ]],
        ], $actual);
    }

    /**
     * Appended objects are snapshots, and repeated IDs replace their entry.
     *
     * @return void
     */
    public function testNetworkSnapshots(): void
    {
        $route = Route::make('default', '192.0.2.1')->setMetric(100);
        $ethernet = Ethernet::make('eth0')->setDhcp4()->appendRoute($route);
        $network = NetworkConfig::make()->setEthernet($ethernet);
        $route->setMetric(200);
        $ethernet->setDhcp4(false);

        self::assertTrue($network->toArray()['ethernets']['eth0']['dhcp4']);
        self::assertSame(100, $ethernet->toArray()['routes'][0]['metric']);
        $network->setEthernet(Ethernet::make('eth0')->setDhcp6());
        self::assertSame(['eth0' => ['dhcp6' => true]], $network->toArray()['ethernets']);
    }

    /**
     * A failed batch validation leaves the prior addresses unchanged.
     *
     * @return void
     */
    public function testNetworkValidationIsAtomic(): void
    {
        $ethernet = Ethernet::make('eth0')->setAddresses(['192.0.2.10/24']);

        try {
            $ethernet->setAddresses(['192.0.2.11/24', 'invalid']);
            self::fail('Invalid CIDR accepted.');
        } catch (Exceptions\NetworkAddressInvalidException) {
            self::assertSame(['192.0.2.10/24'], $ethernet->toArray()['addresses']);
        }
    }

    /**
     * All documents write their exact bytes under the standard NoCloud filenames.
     *
     * @return void
     */
    public function testWriteNoCloudFiles(): void
    {
        $directory = sys_get_temp_dir().'/cloud-init-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $documents = [
            'user-data' => UserData::make()->setHostname('web-01'),
            'meta-data' => MetaData::make()->setInstanceId('iid-01'),
            'network-config' => NetworkConfig::make()->setEthernet(Ethernet::make('eth0')->setDhcp4()),
        ];

        try {
            foreach ($documents as $filename => $document) {
                self::assertSame($directory.'/'.$filename, $document->renderYaml($directory, $filename));
                self::assertSame($document->renderString(), file_get_contents($directory.'/'.$filename));
            }
        } finally {
            foreach (array_keys($documents) as $filename) {
                if (is_file($directory.'/'.$filename)) {
                    unlink($directory.'/'.$filename);
                }
            }

            rmdir($directory);
        }
    }

    /**
     * Supply malformed metadata and network input with the expected exception.
     *
     * @return iterable<string, array{class-string<\CloudInit\Exceptions\ValidationException>, Closure(): mixed}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'missing instance id' => [Exceptions\InstanceIdInvalidException::class, fn () => MetaData::make()->renderString()];
        yield 'blank instance id' => [Exceptions\InstanceIdInvalidException::class, fn () => MetaData::make()->setInstanceId(' ')];
        yield 'blank hostname' => [Exceptions\HostnameInvalidException::class, fn () => MetaData::make()->setLocalHostname('')];
        yield 'empty network' => [Exceptions\NetworkConfigInvalidException::class, fn () => NetworkConfig::make()->renderString()];
        yield 'empty interface' => [Exceptions\NetworkInterfaceInvalidException::class, fn () => Ethernet::make('eth0')->toArray()];
        yield 'numeric id' => [Exceptions\NetworkInterfaceInvalidException::class, fn () => Ethernet::make('0')];
        yield 'blank id' => [Exceptions\NetworkInterfaceInvalidException::class, fn () => Ethernet::make(' ')];
        yield 'invalid mac' => [Exceptions\NetworkInterfaceInvalidException::class, fn () => Ethernet::make('eth0')->setMatchMacAddress('invalid')];
        yield 'rename without match' => [Exceptions\NetworkInterfaceInvalidException::class, fn () => Ethernet::make('eth0')->setName('lan0')->toArray()];
        yield 'invalid rename' => [Exceptions\NetworkInterfaceInvalidException::class, fn () => Ethernet::make('eth0')->setName('../eth0')];
        yield 'invalid mtu' => [Exceptions\NetworkMtuInvalidException::class, fn () => Ethernet::make('eth0')->setMtu(0)];
        yield 'missing prefix' => [Exceptions\NetworkAddressInvalidException::class, fn () => Ethernet::make('eth0')->setAddresses(['192.0.2.1'])];
        yield 'ipv4 prefix' => [Exceptions\NetworkAddressInvalidException::class, fn () => Ethernet::make('eth0')->setAddresses(['192.0.2.1/33'])];
        yield 'ipv6 prefix' => [Exceptions\NetworkAddressInvalidException::class, fn () => Ethernet::make('eth0')->setAddresses(['2001:db8::1/129'])];
        yield 'invalid ip' => [Exceptions\NetworkAddressInvalidException::class, fn () => Ethernet::make('eth0')->setAddresses(['999.0.2.1/24'])];
        yield 'wrong list type' => [Exceptions\NetworkAddressInvalidException::class, fn () => Ethernet::make('eth0')->setAddresses([42])];
        yield 'associative list' => [Exceptions\NetworkAddressInvalidException::class, fn () => Ethernet::make('eth0')->setAddresses(['ip' => '192.0.2.1/24'])];
        yield 'dns cidr' => [Exceptions\NameserverInvalidException::class, fn () => Ethernet::make('eth0')->setNameservers(['192.0.2.53/24'])];
        yield 'empty domain' => [Exceptions\NameserverInvalidException::class, fn () => Ethernet::make('eth0')->setSearchDomains([''])];
        yield 'route family mismatch' => [Exceptions\NetworkRouteInvalidException::class, fn () => Route::make('::/0', '192.0.2.1')];
        yield 'route prefix' => [Exceptions\NetworkRouteInvalidException::class, fn () => Route::make('192.0.2.0/33', '192.0.2.1')];
        yield 'gateway' => [Exceptions\NetworkRouteInvalidException::class, fn () => Route::make('default', 'invalid')];
        yield 'negative metric' => [Exceptions\NetworkRouteInvalidException::class, fn () => Route::make('default', '192.0.2.1')->setMetric(-1)];
    }

    /**
     * Fail predictably with the domain-specific validation exception.
     *
     * @param  class-string<\CloudInit\Exceptions\ValidationException>  $exception  Expected exception class.
     * @param  \Closure(): mixed  $operation  Invalid builder operation.
     * @return void
     */
    #[DataProvider('invalidInputs')]
    public function testInvalidInput(string $exception, Closure $operation): void
    {
        $this->expectException($exception);
        $operation();
    }
}
