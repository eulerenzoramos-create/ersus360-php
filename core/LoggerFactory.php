<?php

declare(strict_types=1);

namespace Ersus360\Core;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Level;

final class LoggerFactory
{
    public static function create(string $channel = 'ersus360'): Logger
    {
        $debug   = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        $level   = $debug ? Level::Debug : Level::Info;

        $handler = new StreamHandler('php://stdout', $level);
        $handler->setFormatter(new JsonFormatter());

        $logger = new Logger($channel);
        $logger->pushHandler($handler);

        return $logger;
    }
}
