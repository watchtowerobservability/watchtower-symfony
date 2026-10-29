<?php

declare(strict_types=1);

namespace Watchtower\Symfony;

use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use Throwable;
use Watchtower\Config;
use WeakMap;

/** Records exceptions on the active span (or a standalone one) and wraps work in spans. */
final class Reporter
{
    /** @var WeakMap<Throwable, string> */
    private WeakMap $seen;

    public function __construct(private readonly TracerInterface $tracer)
    {
        $this->seen = new WeakMap();
    }

    /** @param array<string, mixed> $attrs wt.* keys are dropped. Returns the trace id. */
    public function capture(Throwable $e, array $attrs = []): string
    {
        if (isset($this->seen[$e])) {
            return $this->seen[$e];
        }
        $span = Span::getCurrent();
        $own = !$span->getContext()->isValid();
        if ($own) {
            $span = $this->tracer->spanBuilder('exception')->startSpan();
        }
        $span->recordException($e, Config::redact($attrs));
        $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
        $traceId = $span->getContext()->getTraceId();
        if ($own) {
            $span->end();
        }
        return $this->seen[$e] = $traceId;
    }

    /**
     * Runs $fn inside an active span; an exception is recorded on that span and rethrown.
     * @param array<string, scalar> $attrs
     * @param callable(): mixed $fn
     */
    public function around(string $name, int $kind, array $attrs, callable $fn): mixed
    {
        $span = $this->tracer->spanBuilder($name)->setSpanKind($kind)->setAttributes(Config::dropReserved($attrs))->startSpan();
        $scope = $span->activate();
        try {
            return $fn();
        } catch (Throwable $e) {
            $this->capture($e);
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
