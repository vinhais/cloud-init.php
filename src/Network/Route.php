<?php

declare(strict_types=1);

namespace CloudInit\Network;

use CloudInit\Concerns\Conditionable;
use CloudInit\Exceptions\NetworkRouteInvalidException;
use CloudInit\Support\NetworkValidation;

/**
 * Build one static routes[] entry for network-config Version 2.
 *
 * @see https://docs.cloud-init.io/en/latest/reference/network-config-format-v2.html
 */
final class Route
{
    use Conditionable;

    /**
     * Validated route options.
     *
     * @var array{to: string, via: string, metric?: int}
     */
    private array $options;

    /**
     * Validate the destination and gateway before creating the route.
     *
     * @param  string  $to  CIDR destination or the literal default.
     * @param  string  $via  Gateway IP address in the same address family.
     * @return void
     *
     * @throws \CloudInit\Exceptions\NetworkRouteInvalidException
     */
    private function __construct(string $to, string $via)
    {
        $family = NetworkValidation::ip($via, NetworkRouteInvalidException::class);

        if ($to !== 'default' && NetworkValidation::cidr($to, NetworkRouteInvalidException::class) !== $family) {
            throw new NetworkRouteInvalidException('Route destination and gateway must use the same IP family.');
        }

        $this->options = ['to' => $to, 'via' => $via];
    }

    /**
     * Create a route using routes[].to and routes[].via.
     *
     * @param  string  $to  CIDR destination or default.
     * @param  string  $via  Gateway IP address.
     * @return static
     *
     * @throws \CloudInit\Exceptions\NetworkRouteInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/network-config-format-v2.html
     */
    public static function make(string $to, string $via): self
    {
        return new self($to, $via);
    }

    /**
     * Set routes[].metric, preserving zero as a valid metric.
     *
     * @param  int  $metric  Non-negative routing metric.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\NetworkRouteInvalidException
     */
    public function setMetric(int $metric): self
    {
        if ($metric < 0) {
            throw new NetworkRouteInvalidException('Route metric must be non-negative.');
        }

        $this->options['metric'] = $metric;

        return $this;
    }

    /**
     * Return a snapshot of the route.
     *
     * @return array{to: string, via: string, metric?: int}
     */
    public function toArray(): array
    {
        return $this->options;
    }
}
