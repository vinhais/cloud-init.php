<?php

declare(strict_types=1);

namespace CloudInit;

use CloudInit\Concerns\Conditionable;
use CloudInit\Concerns\RendersYaml;
use CloudInit\Exceptions\NetworkConfigInvalidException;
use CloudInit\Network\Ethernet;
use Stringable;

/**
 * Build a standalone NoCloud network-config Version 2 document.
 *
 * @phpstan-import-type EthernetOptions from Ethernet
 *
 * @see https://docs.cloud-init.io/en/latest/reference/network-config-format-v2.html
 * @see https://docs.cloud-init.io/en/latest/reference/datasources/nocloud.html
 */
final class NetworkConfig implements Stringable
{
    use Conditionable;
    use RendersYaml;

    /**
     * Ethernet snapshots keyed by their unique IDs.
     *
     * @var array<string, EthernetOptions>
     */
    private array $ethernets = [];

    /**
     * Create a Version 2 builder; configure an Ethernet interface before rendering.
     *
     * @return static
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Set ethernets[id], replacing a previous entry with the same ID.
     *
     * @param  \CloudInit\Network\Ethernet  $ethernet  Interface copied into this document.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\NetworkInterfaceInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/network-config-format-v2.html
     */
    public function setEthernet(Ethernet $ethernet): self
    {
        $this->ethernets[$ethernet->getId()] = $ethernet->toArray();

        return $this;
    }

    /**
     * Return standalone network-config; NoCloud does not use a network wrapper.
     *
     * @return array<string, mixed>
     *
     * @phpstan-return array{version: 2, ethernets: non-empty-array<string, EthernetOptions>}
     *
     * @throws \CloudInit\Exceptions\NetworkConfigInvalidException
     */
    public function toArray(): array
    {
        if ($this->ethernets === []) {
            throw new NetworkConfigInvalidException('Configure at least one Ethernet interface before rendering network-config.');
        }

        return ['version' => 2, 'ethernets' => $this->ethernets];
    }
}
