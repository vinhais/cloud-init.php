<?php

declare(strict_types=1);

namespace CloudInit\Datasources;

use CloudInit\Concerns\Conditionable;
use CloudInit\Concerns\RendersYaml;
use CloudInit\Exceptions\LxdConfigInvalidException;
use CloudInit\NetworkConfig;
use CloudInit\UserData;
use Stringable;

/**
 * Export cloud-init settings inside an LXD config mapping.
 *
 * @see https://docs.cloud-init.io/en/latest/reference/datasources/lxd.html
 */
final class Lxd implements Stringable
{
    use Conditionable;
    use RendersYaml;

    /**
     * Rendered document snapshots, without the LXD key prefix.
     *
     * @var array<string, string>
     */
    private array $documents = [];

    /**
     * Whether to use the user.* keys required by older images.
     *
     * @var bool
     */
    private bool $legacyKeys = false;

    /**
     * Create an empty LXD configuration builder.
     *
     * @return static
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Set cloud-init.user-data as a literal YAML string snapshot.
     *
     * @param  \CloudInit\UserData  $userData  Instance-specific cloud-config.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\YamlRenderException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/datasources/lxd.html
     */
    public function setUserData(UserData $userData): self
    {
        $this->documents['user-data'] = $userData->renderString();

        return $this;
    }

    /**
     * Set cloud-init.vendor-data as a separate cloud-config string snapshot.
     *
     * @param  \CloudInit\UserData  $vendorData  Default configuration merged by cloud-init.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\YamlRenderException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/datasources/lxd.html
     */
    public function setVendorData(UserData $vendorData): self
    {
        $this->documents['vendor-data'] = $vendorData->renderString();

        return $this;
    }

    /**
     * Set cloud-init.network-config as a standalone Version 2 YAML snapshot.
     *
     * @param  \CloudInit\NetworkConfig  $network  Network settings without an outer network key.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\NetworkConfigInvalidException
     * @throws \CloudInit\Exceptions\YamlRenderException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/datasources/lxd.html
     */
    public function setNetworkConfig(NetworkConfig $network): self
    {
        $this->documents['network-config'] = $network->renderString();

        return $this;
    }

    /**
     * Switch all document keys between cloud-init.* and legacy user.* names.
     *
     * @param  bool  $enabled  True for images requiring legacy user.* keys.
     * @return $this
     *
     * @see https://documentation.ubuntu.com/lxd/latest/cloud-init/
     */
    public function useLegacyKeys(bool $enabled = true): self
    {
        $this->legacyKeys = $enabled;

        return $this;
    }

    /**
     * Return the config fragment to merge into an LXD instance or profile.
     *
     * @return array{config: non-empty-array<string, string>}
     *
     * @throws \CloudInit\Exceptions\LxdConfigInvalidException
     */
    public function toArray(): array
    {
        if ($this->documents === []) {
            throw new LxdConfigInvalidException('Configure user-data, vendor-data or network-config before exporting LXD settings.');
        }

        $config = [];
        $prefix = $this->legacyKeys ? 'user.' : 'cloud-init.';

        foreach ($this->documents as $name => $document) {
            $config[$prefix.$name] = $document;
        }

        return ['config' => $config];
    }
}
