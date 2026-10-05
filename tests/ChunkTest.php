<?php

declare(strict_types=1);

namespace CloudInit\Tests;

use CloudInit\CloudInit;
use CloudInit\Exceptions\InstanceIdInvalidException;
use CloudInit\Exceptions\NetworkConfigInvalidException;
use CloudInit\MetaData;
use CloudInit\Network\Ethernet;
use CloudInit\NetworkConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Verify lossless packaging, snapshot isolation and document validation.
 */
final class ChunkTest extends TestCase
{
    /**
     * A single file round-trips all source documents without duplicate headers.
     *
     * @return void
     */
    public function testPackageRoundTrip(): void
    {
        $user = CloudInit::make()->setHostname('web-01')
            ->appendRunCommand(['systemctl', 'enable', '--now', 'nginx']);
        $meta = MetaData::make()->setInstanceId('iid-web-01');
        $network = NetworkConfig::make()->setEthernet(Ethernet::make('eth0')->setDhcp4());
        $expected = [
            'user-data' => $user->renderString(),
            'meta-data' => $meta->renderString(),
            'network-config' => $network->renderString(),
        ];
        $chunk = $user->chunk($meta, $network);

        $user->setHostname('changed');
        $meta->setInstanceId('changed');
        $network->setEthernet(Ethernet::make('eth1')->setDhcp6());

        self::assertSame($expected, $chunk->toArray());
        self::assertSame($expected, Yaml::parse($chunk->renderString()));
        self::assertSame(1, substr_count($chunk->renderString(), '#cloud-config'));
        self::assertSame($chunk->renderString(), $chunk->render());
        self::assertSame($chunk->renderString(), (string) $chunk);
        self::assertSame('web-01', Yaml::parse($chunk->toArray()['user-data'])['hostname']);

        $path = tempnam(sys_get_temp_dir(), 'cloud-init-chunk-');
        self::assertNotFalse($path);

        try {
            self::assertSame($path, $chunk->renderYaml(dirname($path), basename($path)));
            self::assertSame($chunk->renderString(), file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    /**
     * Packaging preserves the required metadata identity validation.
     *
     * @return void
     */
    public function testMissingInstanceId(): void
    {
        $this->expectException(InstanceIdInvalidException::class);

        CloudInit::make()->chunk(
            MetaData::make(),
            NetworkConfig::make()->setEthernet(Ethernet::make('eth0')->setDhcp4())
        );
    }

    /**
     * Packaging rejects an incomplete network document.
     *
     * @return void
     */
    public function testMissingNetworkInterface(): void
    {
        $this->expectException(NetworkConfigInvalidException::class);

        CloudInit::make()->chunk(MetaData::make()->setInstanceId('iid-01'), NetworkConfig::make());
    }
}
