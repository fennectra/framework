<?php

namespace Fennec\Core;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class ErrorHandler
{
    private Logger $logger;
    private string $environment;

    public function __construct(?string $environment = null)
    {
        $this->environment = $environment ?? Env::get('APP_ENV', 'prod');

        $this->logger = new Logger('app');
        $logDir = FENNEC_BASE_PATH . '/var/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $this->logger->pushHandler(new RotatingFileHandler("{$logDir}/app.log", 14, Logger::ERROR));

        if ($this->environment === 'dev') {
            $this->logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));
        }
    }

    /**
     * Enregistre les handlers globaux d'erreurs et d'exceptions.
     */
    public function register(): void
    {
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError']);

        // Capturer les warnings PHP pour eviter qu'ils polluent le JSON
        ob_start();
    }

    /**
     * Gère une exception non capturée.
     */
    public function handleException(\Throwable $e): void
    {
        $result = $this->buildErrorResponse($e);

        // Logger uniquement les vraies erreurs, pas les HttpException attendues
        if (!($e instanceof HttpException)) {
            $this->logger->error($e->getMessage(), [
                'request_id' => $result['response']['request_id'],
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'uri' => $_SERVER['REQUEST_URI'] ?? 'cli',
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'cli',
            ]);
        }

        // Nettoyer tout output precedent (warnings PHP, etc.)
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($result['statusCode']);
            header('Content-Type: application/json');
        }
        echo json_encode($result['response'], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Construit la reponse d'erreur sans effets de bord (testable).
     *
     * @return array{statusCode: int, response: array}
     */
    public function buildErrorResponse(\Throwable $e, ?string $requestId = null): array
    {
        $requestId ??= bin2hex(random_bytes(8));

        if ($e instanceof HttpException) {
            $statusCode = $e->statusCode;
            $message = $e->detail;
        } else {
            $statusCode = 500;
            $message = $this->environment === 'dev'
                ? $e->getMessage()
                : 'Erreur interne du serveur';
        }

        $response = [
            'status' => 'error',
            'message' => $message,
            'request_id' => $requestId,
            'timestamp' => date('c'),
        ];

        if ($e instanceof HttpException && !empty($e->errors)) {
            $response['errors'] = $e->errors;
        }

        // Trace uniquement pour les vraies erreurs (pas les HttpException attendues)
        if ($this->environment === 'dev' && !($e instanceof HttpException)) {
            $response['exception'] = get_class($e);
            $response['file'] = $e->getFile() . ':' . $e->getLine();
            $response['trace'] = array_slice(
                array_map(fn ($frame) => [
                    'file' => ($frame['file'] ?? '?') . ':' . ($frame['line'] ?? '?'),
                    'call' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''),
                ], $e->getTrace()),
                0,
                10
            );
        }

        return ['statusCode' => $statusCode, 'response' => $response];
    }

    /**
     * Convertit les erreurs PHP en exceptions.
     */
    public function handleError(int $severity, string $message, string $file, int $line): never
    {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }
}
