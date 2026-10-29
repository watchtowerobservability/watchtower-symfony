<?php

declare(strict_types=1);

namespace Watchtower\Symfony\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Watchtower\Symfony\ExceptionSubscriber;
use Watchtower\Symfony\WatchtowerBundle;

final class BundleTest extends TestCase
{
    private function load(array $cfg): ContainerBuilder
    {
        $c = new ContainerBuilder();
        // the parameters a real kernel defines; newer http-kernel extensions read them
        foreach (['kernel.environment' => 'test', 'kernel.runtime_environment' => 'test', 'kernel.debug' => true,
            'kernel.project_dir' => __DIR__, 'kernel.cache_dir' => sys_get_temp_dir(), 'kernel.build_dir' => sys_get_temp_dir(),
            'kernel.logs_dir' => sys_get_temp_dir(), 'kernel.charset' => 'UTF-8'] as $k => $v) {
            $c->setParameter($k, $v);
        }
        (new WatchtowerBundle())->getContainerExtension()->load([$cfg], $c);
        return $c;
    }

    public function testServicesAndTagsRegistered(): void
    {
        $c = $this->load(['service' => 'shop']);
        $this->assertSame('shop', $c->getParameter('watchtower.config')['service']);
        foreach (['tracer_provider', 'reporter', 'exception_subscriber', 'messenger_middleware', 'doctrine_middleware', 'monolog_handler'] as $id) {
            $this->assertTrue($c->hasDefinition("watchtower.$id"), $id);
        }
        $this->assertArrayHasKey('doctrine.dbal.middleware', $c->getDefinition('watchtower.doctrine_middleware')->getTags());
        $this->assertArrayHasKey('kernel.event_subscriber', $c->getDefinition('watchtower.exception_subscriber')->getTags());
    }

    public function testKeyDefaultIsEnvPlaceholderNotAValue(): void
    {
        $key = $this->load([])->getParameter('watchtower.config')['key'];
        $this->assertSame('%env(default::WT_INGEST_KEY)%', $key);
    }

    public function testDisabledRegistersNothing(): void
    {
        $this->assertFalse($this->load(['enabled' => false])->hasDefinition('watchtower.reporter'));
    }

    public function testQuerySpansCanBeTurnedOff(): void
    {
        $this->assertFalse($this->load(['query_spans' => false])->hasDefinition('watchtower.doctrine_middleware'));
    }

    public function testSubscribedEvents(): void
    {
        $this->assertArrayHasKey('kernel.exception', ExceptionSubscriber::getSubscribedEvents());
    }
}
