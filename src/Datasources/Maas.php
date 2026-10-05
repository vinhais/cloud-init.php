<?php

declare(strict_types=1);

namespace CloudInit\Datasources;

use CloudInit\UserData;

/**
 * Build cloud-config and encode it for the MAAS machine deploy operation.
 *
 * Metadata, network configuration and authentication remain managed by MAAS.
 *
 * @see https://canonical.com/maas/docs/latest/reference/cli-reference/machine/
 */
final class Maas extends UserData
{
    /**
     * Return the user_data form parameter expected by MAAS machine deploy.
     *
     * @return array{user_data: string} Base64-encoded cloud-config, not raw YAML.
     *
     * @throws \CloudInit\Exceptions\YamlRenderException
     *
     * @see https://canonical.com/maas/docs/latest/reference/api-reference/api-v2-generated/
     */
    public function toDeployParameters(): array
    {
        return ['user_data' => base64_encode($this->renderString())];
    }
}
