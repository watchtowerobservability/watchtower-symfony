<?php

declare(strict_types=1);

namespace Watchtower\Symfony;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use OpenTelemetry\API\Trace\Span;
use Throwable;
use Watchtower\Config;

/** Monolog handler: exceptions in context are captured, other records become events on the active span. */
final class WatchtowerHandler extends AbstractProcessingHandler
{
    public function __construct(private readonly Reporter $reporter, int|string|Level $level = Level::Error)
    {
        parent::__construct($level);
    }

    protected function write(LogRecord $record): void
    {
        $ex = $record->context['exception'] ?? null;
        if ($ex instanceof Throwable) {
            $this->reporter->capture($ex, ['log.message' => $record->message]);
            return;
        }
        $span = Span::getCurrent();
        if ($span->getContext()->isValid()) {
            $scalars = array_filter($record->context, 'is_scalar');
            $span->addEvent('log', Config::redact($scalars) + [
                'log.severity' => $record->level->getName(),
                'log.message' => $record->message,
            ]);
        }
    }
}
