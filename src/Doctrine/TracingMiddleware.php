<?php

declare(strict_types=1);

namespace Watchtower\Symfony\Doctrine;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware;
use Watchtower\Symfony\Reporter;

/** DBAL 4 driver middleware, auto-tagged `doctrine.dbal.middleware`. Bound parameters are never recorded. */
final class TracingMiddleware implements Middleware
{
    public function __construct(private readonly Reporter $reporter)
    {
    }

    public function wrap(DriverInterface $driver): DriverInterface
    {
        return new TracingDriver($driver, $this->reporter);
    }
}
