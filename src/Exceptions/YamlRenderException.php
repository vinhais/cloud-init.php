<?php

declare(strict_types=1);

namespace CloudInit\Exceptions;

use RuntimeException;

/**
 * The YAML serializer could not render the configuration.
 */
final class YamlRenderException extends RuntimeException
{
}
