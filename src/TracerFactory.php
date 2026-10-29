<?php

declare(strict_types=1);

namespace Watchtower\Symfony;

use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use Watchtower\Config;
use Watchtower\Watchtower;

final class TracerFactory
{
    /** @param array<string, mixed> $c resolved `watchtower` bundle config */
    public static function make(array $c): TracerProviderInterface
    {
        $res = array_filter([
            'deployment.environment.name' => $c['environment'] ?? null,
            'service.version' => $c['release'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');
        $endpoint = ($c['endpoint'] ?? '') === '' ? null : $c['endpoint'];
        $key = ($c['key'] ?? '') === '' ? null : $c['key'];
        return Watchtower::init(new Config((string) $c['service'], $endpoint, $key, $res));
    }
}
