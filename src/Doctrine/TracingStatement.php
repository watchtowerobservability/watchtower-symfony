<?php

declare(strict_types=1);

namespace Watchtower\Symfony\Doctrine;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Watchtower\Symfony\Reporter;

final class TracingStatement extends AbstractStatementMiddleware
{
    public function __construct(Statement $statement, private readonly Reporter $reporter, private readonly string $system, private readonly string $sql)
    {
        parent::__construct($statement);
    }

    public function execute(): Result
    {
        return TracingConnection::span($this->reporter, $this->system, $this->sql, fn () => parent::execute());
    }
}
