<?php

declare(strict_types=1);

namespace CloudInit\Exceptions;

use InvalidArgumentException;

/**
 * Base exception for all builder input validation failures.
 */
class ValidationException extends InvalidArgumentException
{
}
