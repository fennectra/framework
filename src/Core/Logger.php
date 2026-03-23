<?php

namespace Fennec\Core;

use Fennec\Core\Logging\LogMaskingProcessor;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;

class Logger
{
    private static ?MonologLogger $instance = null;

    public static function getInstance(): MonologLogger
    {
        if (self::$instance === null) {
            self::$instance = self::create();
        }

        return self::$instance;
    }

    public static function setInstance(MonologLogger $logger): void
    {
        self::$instance = $logger;
    }

    private static function create(): MonologLogger
    {
        $logger = new MonologLogger('app');

        $logDir = FENNEC_BASE_PATH . '/var/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Fichier rotatif par jour — garde 14 jours
        $logger->pushHandler(
            new RotatingFileHandler($logDir . '/app.log', 14, Level::Debug)
        );

        // Stderr pour Docker/K8s (visible dans kubectl logs)
        if (self::isWorkerMode()) {
            $logger->pushHandler(
                new StreamHandler('php://stderr', Level::Warning)
            );
        }

        // Masquage des donnees sensibles dans les logs (SOC 2)
        $extraKeys = array_filter(
            array_map('trim', explode(',', Env::get('LOG_MASK_FIELDS', '')))
        );
        $logger->pushProcessor(new LogMaskingProcessor($extraKeys));

        return $logger;
    }

    private static function isWorkerMode(): bool
    {
        return !empty($_SERVER['FRANKENPHP_WORKER'])
            || function_exists('frankenphp_handle_request');
    }

    // ── Raccourcis statiques ──

    public static function debug(string $message, array $context = []): void
    {
        self::getInstance()->debug($message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::getInstance()->info($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::getInstance()->warning($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::getInstance()->error($message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::getInstance()->critical($message, $context);
    }
}
