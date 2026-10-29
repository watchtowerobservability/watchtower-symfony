<?php

declare(strict_types=1);

namespace Watchtower\Symfony\Messenger;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Watchtower\Symfony\Reporter;

/** Add `watchtower.messenger_middleware` to a bus's middleware list. */
final class TracingMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Reporter $reporter, private readonly TracerProviderInterface $provider)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $consumer = $envelope->last(ReceivedStamp::class) !== null;
        try {
            return $this->reporter->around(
                'messenger.' . ($consumer ? 'process ' : 'publish ') . $envelope->getMessage()::class,
                $consumer ? SpanKind::KIND_CONSUMER : SpanKind::KIND_PRODUCER,
                ['messaging.operation.type' => $consumer ? 'process' : 'publish'],
                static fn () => $stack->next()->handle($envelope, $stack),
            );
        } finally {
            if ($consumer) {
                $this->provider->forceFlush(); // workers never terminate
            }
        }
    }
}
