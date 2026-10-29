<?php

declare(strict_types=1);

namespace Watchtower\Symfony;

use Doctrine\DBAL\Driver\Middleware as DbalMiddleware;
use Monolog\Handler\AbstractProcessingHandler;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Watchtower\Symfony\Doctrine\TracingMiddleware as DoctrineMiddleware;
use Watchtower\Symfony\Messenger\TracingMiddleware as MessengerMiddleware;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class WatchtowerBundle extends AbstractBundle
{
    protected string $extensionAlias = 'watchtower';

    public function configure(DefinitionConfigurator $definition): void
    {
        // env placeholders stay unresolved until runtime so the key never lands in the compiled container
        $definition->rootNode()->children()
            ->booleanNode('enabled')->defaultTrue()->end()
            ->scalarNode('service')->defaultValue('%env(default:watchtower.default_service:WT_SERVICE)%')->end()
            ->scalarNode('endpoint')->defaultValue('%env(default::WT_OTLP_ENDPOINT)%')->end()
            ->scalarNode('key')->defaultValue('%env(default::WT_INGEST_KEY)%')->end()
            ->scalarNode('environment')->defaultValue('%env(default::WT_ENVIRONMENT)%')->end()
            ->scalarNode('release')->defaultValue('%env(default::WT_RELEASE)%')->end()
            ->booleanNode('query_spans')->defaultTrue()->end()
            ->end();
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('watchtower.default_service', 'symfony');
        if (!$config['enabled']) {
            return;
        }
        $builder->setParameter('watchtower.config', $config);
        $s = $container->services();
        $s->set('watchtower.tracer_provider', TracerProvider::class)
            ->factory([TracerFactory::class, 'make'])->args(['%watchtower.config%'])->public();
        $s->set('watchtower.tracer', \OpenTelemetry\API\Trace\TracerInterface::class)
            ->factory([service('watchtower.tracer_provider'), 'getTracer'])->args(['watchtower-symfony']);
        $s->set('watchtower.reporter', Reporter::class)->args([service('watchtower.tracer')])->public();
        $s->alias(Reporter::class, 'watchtower.reporter');
        $s->set('watchtower.exception_subscriber', ExceptionSubscriber::class)
            ->args([service('watchtower.reporter'), service('watchtower.tracer_provider')])
            ->tag('kernel.event_subscriber');

        if (interface_exists(MiddlewareInterface::class)) {
            $s->set('watchtower.messenger_middleware', MessengerMiddleware::class)
                ->args([service('watchtower.reporter'), service('watchtower.tracer_provider')]);
        }
        if ($config['query_spans'] && interface_exists(DbalMiddleware::class)) {
            $s->set('watchtower.doctrine_middleware', DoctrineMiddleware::class)
                ->args([service('watchtower.reporter')])->tag('doctrine.dbal.middleware');
        }
        if (class_exists(AbstractProcessingHandler::class)) {
            $s->set('watchtower.monolog_handler', WatchtowerHandler::class)->args([service('watchtower.reporter')]);
        }
    }
}
