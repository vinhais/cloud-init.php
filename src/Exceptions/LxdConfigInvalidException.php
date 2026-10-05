<?php

declare(strict_types=1);

namespace CloudInit\Exceptions;

/**
 * No cloud-init documents were configured for LXD.
 */
final class LxdConfigInvalidException extends ValidationException
{
}
