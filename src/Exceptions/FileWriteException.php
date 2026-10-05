<?php

declare(strict_types=1);

namespace CloudInit\Exceptions;

use RuntimeException;

/**
 * The rendered YAML could not be completely written to the destination.
 */
final class FileWriteException extends RuntimeException
{
}
