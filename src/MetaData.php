<?php

declare(strict_types=1);

namespace CloudInit;

use CloudInit\Concerns\Conditionable;
use CloudInit\Concerns\RendersYaml;
use CloudInit\Exceptions\HostnameInvalidException;
use CloudInit\Exceptions\InstanceIdInvalidException;
use CloudInit\Support\Validation;
use Stringable;

/**
 * Build a NoCloud meta-data document independently of user-data.
 *
 * @see https://docs.cloud-init.io/en/latest/reference/datasources/nocloud.html
 */
final class MetaData implements Stringable
{
    use Conditionable;
    use RendersYaml;

    /**
     * Explicitly configured instance metadata.
     *
     * @var array{'instance-id'?: string, 'local-hostname'?: string}
     */
    private array $options = [];

    /**
     * Create a metadata builder; set an instance ID before rendering.
     *
     * @return static
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Set instance-id, the stable identity used to recognize an instance.
     *
     * @param  string  $instanceId  Non-blank identifier, for example iid-web-01.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\InstanceIdInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/datasources/nocloud.html#meta-data
     */
    public function setInstanceId(string $instanceId): self
    {
        Validation::notBlank($instanceId, 'Instance ID', InstanceIdInvalidException::class);
        $this->options['instance-id'] = $instanceId;

        return $this;
    }

    /**
     * Set the local-hostname supplied by the datasource.
     *
     * @param  string  $hostname  Non-blank hostname, for example web-01.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\HostnameInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/datasources/nocloud.html
     */
    public function setLocalHostname(string $hostname): self
    {
        Validation::notBlank($hostname, 'Local hostname', HostnameInvalidException::class);
        $this->options['local-hostname'] = $hostname;

        return $this;
    }

    /**
     * Return metadata after checking the NoCloud instance-id requirement.
     *
     * @return array{'instance-id': string, 'local-hostname'?: string}
     *
     * @throws \CloudInit\Exceptions\InstanceIdInvalidException
     */
    public function toArray(): array
    {
        if (!isset($this->options['instance-id'])) {
            throw new InstanceIdInvalidException('Set an instance ID before rendering meta-data.');
        }

        return $this->options;
    }
}
