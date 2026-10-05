<?php

declare(strict_types=1);

namespace CloudInit\Network;

use CloudInit\Concerns\Conditionable;
use CloudInit\Exceptions\NameserverInvalidException;
use CloudInit\Exceptions\NetworkAddressInvalidException;
use CloudInit\Exceptions\NetworkInterfaceInvalidException;
use CloudInit\Exceptions\NetworkMtuInvalidException;
use CloudInit\Support\NetworkValidation;
use CloudInit\Support\Validation;

/**
 * Build an ethernets entry for network-config Version 2.
 *
 * @phpstan-type EthernetOptions array{
 *     dhcp4?: bool, dhcp6?: bool, addresses?: list<string>,
 *     nameservers?: array{addresses?: list<string>, search?: list<string>},
 *     match?: array{macaddress: string}, 'set-name'?: string, mtu?: int,
 *     routes?: list<array{to: string, via: string, metric?: int}>
 * }
 *
 * @see https://docs.cloud-init.io/en/latest/reference/network-config-format-v2.html
 */
final class Ethernet
{
    use Conditionable;

    /**
     * Explicitly configured interface options.
     *
     * @var EthernetOptions
     */
    private array $options = [];

    /**
     * Create an interface with an opaque identifier or Linux interface name.
     *
     * @param  string  $id  Non-blank, non-numeric interface ID.
     * @return void
     *
     * @throws \CloudInit\Exceptions\NetworkInterfaceInvalidException
     */
    private function __construct(private readonly string $id)
    {
        Validation::notBlank($id, 'Interface ID', NetworkInterfaceInvalidException::class);

        if (is_numeric($id) || preg_match('/[\s\x00]/', $id) === 1) {
            throw new NetworkInterfaceInvalidException('Interface ID must be non-numeric and contain no whitespace or null bytes.');
        }
    }

    /**
     * Create an ethernets entry; without match, the ID is the interface name.
     *
     * @param  string  $id  Interface ID, for example eth0 or uplink.
     * @return static
     *
     * @throws \CloudInit\Exceptions\NetworkInterfaceInvalidException
     */
    public static function make(string $id): self
    {
        return new self($id);
    }

    /**
     * Return the ID used as the key under ethernets.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set dhcp4 for this interface.
     *
     * @param  bool  $enabled  Whether to enable IPv4 DHCP.
     * @return $this
     *
     * @see https://docs.cloud-init.io/en/latest/reference/network-config-format-v2.html
     */
    public function setDhcp4(bool $enabled = true): self
    {
        $this->options['dhcp4'] = $enabled;

        return $this;
    }

    /**
     * Set dhcp6 for this interface.
     *
     * @param  bool  $enabled  Whether to enable IPv6 DHCP.
     * @return $this
     *
     * @see https://docs.cloud-init.io/en/latest/reference/network-config-format-v2.html
     */
    public function setDhcp6(bool $enabled = true): self
    {
        $this->options['dhcp6'] = $enabled;

        return $this;
    }

    /**
     * Replace addresses after validating the entire list.
     *
     * @param  list<string>  $addresses  IPv4 or IPv6 addresses with CIDR prefixes.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\NetworkAddressInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/network-config-format-v2.html
     */
    public function setAddresses(array $addresses): self
    {
        $validated = Validation::strings($addresses, 'Addresses', NetworkAddressInvalidException::class);

        foreach ($validated as $address) {
            NetworkValidation::cidr($address, NetworkAddressInvalidException::class);
        }

        $this->options['addresses'] = $validated;

        return $this;
    }

    /**
     * Replace nameservers.addresses while preserving search domains.
     *
     * @param  list<string>  $addresses  DNS server IPv4 or IPv6 literals without prefixes.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\NameserverInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/network-config-format-v2.html
     */
    public function setNameservers(array $addresses): self
    {
        $validated = Validation::strings($addresses, 'Nameservers', NameserverInvalidException::class);

        foreach ($validated as $address) {
            NetworkValidation::ip($address, NameserverInvalidException::class);
        }

        $this->options['nameservers']['addresses'] = $validated;

        return $this;
    }

    /**
     * Replace nameservers.search while preserving DNS server addresses.
     *
     * @param  list<string>  $domains  Non-blank DNS search domains.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\NameserverInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/network-config-format-v2.html
     */
    public function setSearchDomains(array $domains): self
    {
        $this->options['nameservers']['search'] = Validation::strings($domains, 'Search domains', NameserverInvalidException::class);

        return $this;
    }

    /**
     * Match a physical interface using match.macaddress.
     *
     * @param  string  $macAddress  Six hexadecimal octets separated by colons.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\NetworkInterfaceInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/network-config-format-v2.html
     */
    public function setMatchMacAddress(string $macAddress): self
    {
        if (preg_match('/^(?:[0-9a-f]{2}:){5}[0-9a-f]{2}$/iD', $macAddress) !== 1) {
            throw new NetworkInterfaceInvalidException('Expected a colon-separated MAC address.');
        }

        $this->options['match'] = ['macaddress' => $macAddress];

        return $this;
    }

    /**
     * Set set-name; a MAC match must also be configured before exporting.
     *
     * @param  string  $name  Linux interface name of at most 15 ASCII characters.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\NetworkInterfaceInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/network-config-format-v2.html
     */
    public function setName(string $name): self
    {
        if (preg_match('/^[a-zA-Z0-9_][a-zA-Z0-9_.-]{0,14}$/D', $name) !== 1) {
            throw new NetworkInterfaceInvalidException('Expected a Linux interface name of at most 15 characters.');
        }

        $this->options['set-name'] = $name;

        return $this;
    }

    /**
     * Set mtu for this interface.
     *
     * @param  int  $mtu  Positive maximum transmission unit.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\NetworkMtuInvalidException
     */
    public function setMtu(int $mtu): self
    {
        if ($mtu < 1) {
            throw new NetworkMtuInvalidException('MTU must be positive.');
        }

        $this->options['mtu'] = $mtu;

        return $this;
    }

    /**
     * Append a snapshot to routes, preserving insertion order.
     *
     * @param  \CloudInit\Network\Route  $route  Validated route entry.
     * @return $this
     *
     * @see https://docs.cloud-init.io/en/latest/reference/network-config-format-v2.html
     */
    public function appendRoute(Route $route): self
    {
        $this->options['routes'][] = $route->toArray();

        return $this;
    }

    /**
     * Return a snapshot after validating interface renaming requirements.
     *
     * @return array<string, mixed>
     *
     * @phpstan-return EthernetOptions
     *
     * @throws \CloudInit\Exceptions\NetworkInterfaceInvalidException
     */
    public function toArray(): array
    {
        if (isset($this->options['set-name']) && !isset($this->options['match'])) {
            throw new NetworkInterfaceInvalidException('Set a MAC address match before exporting an interface with set-name.');
        }

        if ($this->options === []) {
            throw new NetworkInterfaceInvalidException('Configure at least one option before exporting an Ethernet interface.');
        }

        return $this->options;
    }
}
