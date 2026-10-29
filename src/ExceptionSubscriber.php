<?php

declare(strict_types=1);

namespace Watchtower\Symfony;

use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/** Reports unhandled kernel and console exceptions, and flushes at the end of the request or command (PHP-FPM exits). */
final class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Reporter $reporter, private readonly TracerProviderInterface $provider)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // console events are string names so symfony/console stays optional
        return [
            KernelEvents::EXCEPTION => 'onException',
            KernelEvents::TERMINATE => 'flush',
            'console.error' => 'onConsoleError',
            'console.terminate' => 'flush',
        ];
    }

    public function onException(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();
        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            return; // 4xx is the client's problem, not an error to triage
        }
        $this->reporter->capture($e, ['http.route' => (string) $event->getRequest()->attributes->get('_route', '')]);
    }

    /** @param object $event Symfony\Component\Console\Event\ConsoleErrorEvent */
    public function onConsoleError(object $event): void
    {
        $this->reporter->capture($event->getError());
    }

    public function flush(): void
    {
        $this->provider->forceFlush();
    }
}
