<?php

declare(strict_types=1);

namespace Watchtower\Symfony\Doctrine;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use OpenTelemetry\API\Trace\SpanKind;
use Watchtower\Symfony\Reporter;

final class TracingConnection extends AbstractConnectionMiddleware
{
    public function __construct(ConnectionInterface $connection, private readonly Reporter $reporter, private readonly string $system)
    {
        parent::__construct($connection);
    }

    public function prepare(string $sql): Statement
    {
        return new TracingStatement(parent::prepare($sql), $this->reporter, $this->system, $sql);
    }

    public function query(string $sql): Result
    {
        return self::span($this->reporter, $this->system, $sql, fn () => parent::query($sql));
    }

    public function exec(string $sql): int|string
    {
        return self::span($this->reporter, $this->system, $sql, fn () => parent::exec($sql));
    }

    /** @param callable(): mixed $fn */
    public static function span(Reporter $r, string $system, string $sql, callable $fn): mixed
    {
        $op = strtoupper((string) strtok(ltrim($sql), " \t\n("));
        return $r->around($op !== '' ? $op : 'query', SpanKind::KIND_CLIENT, [
            'db.system.name' => $system,
            'db.query.text' => $sql,
        ], $fn);
    }
}
