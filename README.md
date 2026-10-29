# watchtowerobservability/symfony

Watchtower for Symfony, over `watchtowerobservability/sdk` (the thin OpenTelemetry PHP distribution).
Kernel and console exceptions with trace ids, Messenger and Doctrine spans, and a Monolog handler.

Status: 0.x, early. APIs may change between minor versions. Requires Symfony 6.4 or 7, PHP 8.1+.

## Install

```sh
composer config repositories.watchtower-php vcs https://github.com/watchtowerobservability/watchtower-php
composer config repositories.watchtower-symfony vcs https://github.com/watchtowerobservability/watchtower-symfony
composer require watchtowerobservability/symfony
```

## Quick start

```php
// config/bundles.php
Watchtower\Symfony\WatchtowerBundle::class => ['all' => true],
```

```dotenv
WT_INGEST_KEY=...            # secret ingest key; keep it in .env.local, never commit it
WT_OTLP_ENDPOINT=https://ingest.example.com
```

```yaml
# config/packages/framework.yaml: Messenger spans
framework:
    messenger:
        buses:
            messenger.bus.default:
                middleware: [watchtower.messenger_middleware]
# config/packages/monolog.yaml: error logs
monolog:
    handlers:
        watchtower: { type: service, id: watchtower.monolog_handler }
```

Inject `Watchtower\Symfony\Reporter` and call `capture($e)` for handled exceptions; it returns the trace id.

## Integrations

| Feature | How |
|---|---|
| Kernel exceptions | event subscriber; 4xx `HttpException`s are skipped; flush on `kernel.terminate` |
| Console errors | `console.error` and `console.terminate` events |
| Messenger | `watchtower.messenger_middleware` (publish and process spans; flush after each consumed message) |
| Doctrine DBAL 4 | driver middleware auto-tagged `doctrine.dbal.middleware`; bindings never recorded |
| Monolog | `watchtower.monolog_handler`: exceptions captured, other records become span events |

## Configuration

Bundle config `watchtower:` (all optional) with env fallbacks resolved at runtime, so the key is never baked into the compiled container.

| Option | Env | Default |
|---|---|---|
| `key` | `WT_INGEST_KEY` | none |
| `endpoint` | `WT_OTLP_ENDPOINT` | `http://localhost:4318` |
| `service` | `WT_SERVICE` | `symfony` |
| `environment` | `WT_ENVIRONMENT` | none |
| `release` | `WT_RELEASE` | none |
| `enabled` | | `true` (false registers nothing, so remove the handler/middleware references too) |
| `query_spans` | | `true` |

## Provenance

Every release artifact carries a GitHub build attestation. Verify one with
`gh attestation verify <file> --repo watchtowerobservability/watchtower-symfony`.

## License

Apache-2.0. Source of truth is the Watchtower monorepo; this repository is a published mirror.
