<?php

declare(strict_types=1);

namespace Watchtower\Symfony\Tests;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Monolog\Logger;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Watchtower\Symfony\Doctrine\TracingMiddleware as DoctrineMiddleware;
use Watchtower\Symfony\ExceptionSubscriber;
use Watchtower\Symfony\Messenger\TracingMiddleware as MessengerMiddleware;
use Watchtower\Symfony\Reporter;
use Watchtower\Symfony\WatchtowerHandler;

final class IntegrationTest extends TestCase
{
    private InMemoryExporter $exporter;
    private TracerProvider $provider;
    private Reporter $reporter;

    protected function setUp(): void
    {
        $this->exporter = new InMemoryExporter();
        $this->provider = new TracerProvider(new SimpleSpanProcessor($this->exporter));
        $this->reporter = new Reporter($this->provider->getTracer('t'));
    }

    private function exceptionEvents(): int
    {
        $n = 0;
        foreach ($this->exporter->getSpans() as $s) {
            foreach ($s->getEvents() as $e) {
                $n += $e->getName() === 'exception' ? 1 : 0;
            }
        }
        return $n;
    }

    private function event(\Throwable $t): ExceptionEvent
    {
        return new ExceptionEvent($this->createMock(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $t);
    }

    public function testSubscriberReportsServerErrorsOnly(): void
    {
        $sub = new ExceptionSubscriber($this->reporter, $this->provider);
        $sub->onException($this->event(new NotFoundHttpException('nope')));
        $this->assertSame(0, $this->exceptionEvents());
        $sub->onException($this->event(new RuntimeException('boom')));
        $this->assertSame(1, $this->exceptionEvents());
    }

    public function testMessengerMiddlewareRecordsHandlerFailure(): void
    {
        $leaf = new class implements MiddlewareInterface {
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                throw new RuntimeException('handler failed');
            }
        };
        $mw = new MessengerMiddleware($this->reporter, $this->provider);
        $env = (new Envelope(new \stdClass()))->with(new ReceivedStamp('async'));
        try {
            $mw->handle($env, new StackMiddleware($leaf));
            $this->fail('expected exception');
        } catch (RuntimeException) {
        }
        $this->assertSame('messenger.process stdClass', $this->exporter->getSpans()[0]->getName());
        $this->assertSame(1, $this->exceptionEvents());
    }

    public function testDoctrineQuerySpanWithoutParameterValues(): void
    {
        $cfg = (new Configuration())->setMiddlewares([new DoctrineMiddleware($this->reporter)]);
        $conn = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $cfg);
        $conn->executeQuery('SELECT ? AS secret', ['hunter2']);
        $span = $this->exporter->getSpans()[0];
        $this->assertSame('SELECT', $span->getName());
        $this->assertSame('sqlite', $span->getAttributes()->get('db.system.name'));
        $this->assertStringNotContainsString('hunter2', json_encode($span->getAttributes()->toArray()));
    }

    public function testMonologHandlerCapturesException(): void
    {
        $log = new Logger('t', [new WatchtowerHandler($this->reporter)]);
        $log->error('failed', ['exception' => new RuntimeException('logged')]);
        $this->assertSame(1, $this->exceptionEvents());
    }
}
