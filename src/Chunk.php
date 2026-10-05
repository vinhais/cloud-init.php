<?php

declare(strict_types=1);

namespace CloudInit;

use CloudInit\Concerns\RendersYaml;
use Stringable;

/**
 * Package three named cloud-init documents in one application-level YAML file.
 *
 * Only the embedded user-data has a cloud-config header. Extract the named
 * document strings before supplying them to a datasource such as NoCloud.
 *
 * @see https://docs.cloud-init.io/en/latest/reference/datasources/nocloud.html
 */
final class Chunk implements Stringable
{
    use RendersYaml;

    /**
     * Rendered snapshots of the source documents.
     *
     * @var array{'user-data': string, 'meta-data': string, 'network-config': string}
     */
    private readonly array $documents;

    /**
     * Validate and snapshot all documents without changing their source builders.
     *
     * @param  \CloudInit\UserData  $userData  Cloud-config document.
     * @param  \CloudInit\MetaData  $metaData  Instance metadata.
     * @param  \CloudInit\NetworkConfig  $networkConfig  Standalone Version 2 networking.
     * @return void
     *
     * @throws \CloudInit\Exceptions\ValidationException
     * @throws \CloudInit\Exceptions\YamlRenderException
     */
    public function __construct(UserData $userData, MetaData $metaData, NetworkConfig $networkConfig)
    {
        $this->documents = [
            'user-data' => $userData->renderString(),
            'meta-data' => $metaData->renderString(),
            'network-config' => $networkConfig->renderString(),
        ];
    }

    /**
     * Return each document's exact contents keyed by its NoCloud filename.
     *
     * @return array{'user-data': string, 'meta-data': string, 'network-config': string}
     */
    public function toArray(): array
    {
        return $this->documents;
    }
}
