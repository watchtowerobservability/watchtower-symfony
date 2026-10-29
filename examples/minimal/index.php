<?php

// Run: composer install && WT_INGEST_KEY=... php -S localhost:8000 index.php, then open /boom
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Attribute\Route;
use Watchtower\Symfony\Reporter;
use Watchtower\Symfony\WatchtowerBundle;

require __DIR__ . '/vendor/autoload_runtime.php';

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new WatchtowerBundle();
    }

    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader): void
    {
        $c->loadFromExtension('framework', ['secret' => 'example', 'http_method_override' => false, 'router' => ['utf8' => true]]);
        $c->loadFromExtension('watchtower', ['service' => 'example']); // key and endpoint come from WT_INGEST_KEY / WT_OTLP_ENDPOINT
        $c->register('boom', Boom::class)->setPublic(true)->setAutowired(true)->addTag('controller.service_arguments');
    }

    protected function configureRoutes(\Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator $routes): void
    {
        $routes->add('boom', '/boom')->controller('boom');
    }
}

class Boom
{
    public function __construct(private Reporter $reporter)
    {
    }

    public function __invoke(): Response
    {
        throw new RuntimeException('example failure'); // reported by the exception subscriber with its trace id
    }
}

return static fn (array $context) => new Kernel($context['APP_ENV'] ?? 'dev', true);
