<?php

declare(strict_types=1);

namespace CloudInit\Support;

use CloudInit\Exceptions\ValidationException;

/**
 * Shared validation for fluent builders.
 *
 * @internal
 */
final class Validation
{
    /**
     * Reject empty or whitespace-only values without changing their content.
     *
     * @param  string  $value  String to validate without modifying it.
     * @param  string  $field  Field name included in the validation error message.
     * @param  class-string<\CloudInit\Exceptions\ValidationException>  $exception  Validation exception class to throw for invalid input.
     * @return void
     *
     * @throws \CloudInit\Exceptions\ValidationException
     */
    public static function notBlank(string $value, string $field, string $exception): void
    {
        if (trim($value) === '') {
            throw new $exception("{$field} must not be blank.");
        }
    }

    /**
     * Validate every item before changing builder state.
     *
     * @param  array<mixed>  $values  Candidate list whose items must be non-blank strings.
     * @param  string  $field  Field name included in the validation error message.
     * @param  class-string<\CloudInit\Exceptions\ValidationException>  $exception  Validation exception class to throw for invalid input.
     * @return list<non-empty-string>
     *
     * @throws \CloudInit\Exceptions\ValidationException
     */
    public static function strings(array $values, string $field, string $exception): array
    {
        if (!array_is_list($values)) {
            throw new $exception("{$field} must be a list.");
        }

        $result = [];
        foreach ($values as $value) {
            if (!is_string($value) || $value === '' || trim($value) === '') {
                throw new $exception("{$field} must contain non-blank strings.");
            }

            $result[] = $value;
        }

        return $result;
    }
}
