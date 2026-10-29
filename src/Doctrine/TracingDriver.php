<?php

declare(strict_types=1);

namespace Watchtower\Symfony\Doctrine;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Watchtower\Symfony\Reporter;

final class TracingDriver extends AbstractDriverMiddleware
{
    public function __construct(DriverInterface $driver, private readonly Reporter $reporter)
    {
        parent::__construct($driver);
    }

    public function connect(array $params): ConnectionInterface
    {
        $system = preg_replace('/^pdo_?/', '', (string) ($params['driver'] ?? 'sql'));
        return new TracingConnection(parent::connect($params), $this->reporter, $system);
    }
}
