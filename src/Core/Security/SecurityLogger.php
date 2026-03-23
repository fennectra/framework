<?php

namespace Fennec\Core\Security;

use Fennec\Core\Env;
use Fennec\Core\Logging\LogMaskingProcessor;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

/**
 * Logger dedie aux evenements de securite (SOC 2 + ISO 27001).
 *
 * Canal Monolog separe 'security' ecrivant dans security.log + stderr (K8s).
 * Inclut le LogMaskingProcessor et un HMAC d'integrite sur chaque entree.
 *
 * Usage :
 *   SecurityLogger::alert('auth.failed', ['email' => $email, 'ip' => $ip]);
 *   SecurityLogger::track('token.revoked', ['user_id' => 42]);
 */
class SecurityLogger
{
    private static ?Logger $instance = null;
    private static string $previousHash = '';

    public static function getInstance(): Logger
    {
        if (self::$instance === null) {
            self::$instance = self::create();
        }

        return self::$instance;
    }

    public static function setInstance(Logger $logger): void
    {
        self::$instance = $logger;
    }

    /**
     * Reset l'etat inter-requete du logger (HMAC chain).
     *
     * A appeler au debut de chaque requete en mode worker pour isoler
     * les chaines HMAC par requete.
     */
    public static function resetRequestState(): void
    {
        self::$previousHash = '';
    }

    private static function create(): Logger
    {
        $logger = new Logger('security');

        $logDir = FENNEC_BASE_PATH . '/var/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logger->pushHandler(
            new RotatingFileHandler($logDir . '/security.log', 90, Level::Info)
        );

        // Stderr pour Docker/K8s (kubectl logs)
        if (self::isWorkerMode()) {
            $logger->pushHandler(
                new StreamHandler('php://stderr', Level::Warning)
            );
        }

        // ISO 27001 A.8.12 — Masquage des donnees sensibles
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

    /**
     * Log un evenement de securite critique (auth fail, unauthorized access).
     */
    public static function alert(string $event, array $context = []): void
    {
        self::getInstance()->warning($event, self::enrich($context));
    }

    /**
     * Log un evenement de securite informatif (token revoked, password changed).
     */
    public static function track(string $event, array $context = []): void
    {
        self::getInstance()->info($event, self::enrich($context));
    }

    /**
     * Log un evenement de securite critique (intrusion, brute force).
     */
    public static function critical(string $event, array $context = []): void
    {
        self::getInstance()->critical($event, self::enrich($context));
    }

    /**
     * Enrichit le context avec les infos de la requete courante
     * et un HMAC d'integrite (ISO 27001 A.8.15).
     */
    private static function enrich(array $context): array
    {
        $enriched = array_merge($context, [
            'request_id' => $_SERVER['X_REQUEST_ID'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'user' => $_REQUEST['__auth_user']['email'] ?? null,
            'timestamp' => date('c'),
        ]);

        // HMAC chain — chaque entree inclut le hash de la precedente
        $payload = json_encode($enriched, JSON_UNESCAPED_UNICODE);
        $key = Env::get('SECRET_KEY', 'fennec-default');
        $hmac = hash_hmac('sha256', self::$previousHash . $payload, $key);
        self::$previousHash = $hmac;

        $enriched['_hmac'] = $hmac;

        return $enriched;
    }
}
