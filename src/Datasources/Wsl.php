<?php

declare(strict_types=1);

namespace CloudInit\Datasources;

use CloudInit\Exceptions\WslInstanceNameInvalidException;
use CloudInit\MetaData;
use CloudInit\Support\Validation;
use CloudInit\UserData;

/**
 * Build WSL cloud-config and optionally write per-instance metadata beside it.
 *
 * WSL does not consume network-config or vendor-data files from this adapter.
 *
 * @see https://docs.cloud-init.io/en/latest/reference/datasources/wsl.html
 */
final class Wsl extends UserData
{
    /**
     * Instance name used for Windows-side cloud-init filenames.
     *
     * @var string|null
     */
    private ?string $instanceName = null;

    /**
     * Optional metadata snapshot, independent of the original builder.
     *
     * @var \CloudInit\MetaData|null
     */
    private ?MetaData $metaData = null;

    /**
     * Set the WSL instance name without changing the Linux hostname.
     *
     * @param  string  $name  Instance name, for example Ubuntu-24.04.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\WslInstanceNameInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/datasources/wsl.html#user-data-configuration
     */
    public function setInstanceName(string $name): self
    {
        Validation::notBlank($name, 'WSL instance name', WslInstanceNameInvalidException::class);

        if (strpbrk($name, '<>:"/\\|?*') !== false
            || preg_match('/[\x00-\x1f]/', $name) === 1
            || str_ends_with($name, '.')
            || str_ends_with($name, ' ')
            || preg_match('/^(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i', $name) === 1
        ) {
            throw new WslInstanceNameInvalidException('WSL instance name must form a valid Windows filename.');
        }

        $this->instanceName = $name;

        return $this;
    }

    /**
     * Set an optional per-instance metadata snapshot with an explicit instance-id.
     *
     * @param  \CloudInit\MetaData  $metaData  Metadata to write alongside user-data.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\InstanceIdInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/datasources/wsl.html#vendor-data-and-meta-data
     */
    public function setMetaData(MetaData $metaData): self
    {
        $metaData->toArray();
        $this->metaData = clone $metaData;

        return $this;
    }

    /**
     * Write per-instance files into an existing Windows-side .cloud-init directory.
     *
     * Writes are independent; an earlier file remains if a later write fails.
     *
     * @param  string  $directory  Host directory or its mounted path as visible to PHP.
     * @return array{'user-data': string, 'meta-data'?: string} Absolute paths of written files.
     *
     * @throws \CloudInit\Exceptions\WslInstanceNameInvalidException
     * @throws \CloudInit\Exceptions\DirectoryInvalidException
     * @throws \CloudInit\Exceptions\FilenameInvalidException
     * @throws \CloudInit\Exceptions\FileWriteException
     * @throws \CloudInit\Exceptions\InstanceIdInvalidException
     * @throws \CloudInit\Exceptions\YamlRenderException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/datasources/wsl.html
     */
    public function writeFiles(string $directory): array
    {
        if ($this->instanceName === null) {
            throw new WslInstanceNameInvalidException('Set a WSL instance name before writing per-instance files.');
        }

        $paths = ['user-data' => $this->renderYaml($directory, $this->instanceName.'.user-data')];

        if ($this->metaData !== null) {
            $paths['meta-data'] = $this->metaData->renderYaml($directory, $this->instanceName.'.meta-data');
        }

        return $paths;
    }
}
