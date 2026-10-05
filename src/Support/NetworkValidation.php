<?php

declare(strict_types=1);

namespace CloudInit\Support;

use CloudInit\Exceptions\ValidationException;

/**
 * Validate IP addresses without depending on the host network configuration.
 *
 * @internal
 */
final class NetworkValidation
{
    /**
     * Validate an IPv4 or IPv6 literal and return its family.
     *
     * @param  string  $address  Unbracketed IP address without a prefix.
     * @param  class-string<\CloudInit\Exceptions\ValidationException>  $exception  Exception used for invalid input.
     * @return 4|6
     *
     * @throws \CloudInit\Exceptions\ValidationException
     */
    public static function ip(string $address, string $exception): int
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return 4;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return 6;
        }

        throw new $exception('Expected a valid IPv4 or IPv6 address.');
    }

    /**
     * Validate an address and its mandatory CIDR prefix, returning the IP family.
     *
     * @param  string  $address  Address such as 192.0.2.10/24 or 2001:db8::10/64.
     * @param  class-string<\CloudInit\Exceptions\ValidationException>  $exception  Exception used for invalid input.
     * @return 4|6
     *
     * @throws \CloudInit\Exceptions\ValidationException
     */
    public static function cidr(string $address, string $exception): int
    {
        $parts = explode('/', $address);

        if (count($parts) !== 2 || preg_match('/^[0-9]{1,3}$/D', $parts[1]) !== 1) {
            throw new $exception('Expected an IP address with a CIDR prefix.');
        }

        $family = self::ip($parts[0], $exception);

        if ((int) $parts[1] > ($family === 4 ? 32 : 128)) {
            throw new $exception('CIDR prefix exceeds the address family limit.');
        }

        return $family;
    }
}
