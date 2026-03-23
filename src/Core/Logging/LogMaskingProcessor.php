<?php

namespace Fennec\Core\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Processor Monolog qui masque les valeurs sensibles dans les logs.
 *
 * Les cles contenant password, token, secret, authorization, credit_card, ssn, api_key
 * sont remplacees par '***'.
 */
class LogMaskingProcessor implements ProcessorInterface
{
    private const DEFAULT_SENSITIVE_KEYS = [
        'password',
        'token',
        'secret',
        'authorization',
        'credit_card',
        'ssn',
        'api_key',
        'access_token',
        'refresh_token',
    ];

    /** @var string[] */
    private array $sensitiveKeys;

    /**
     * @param string[] $extraKeys Cles supplementaires a masquer
     */
    public function __construct(array $extraKeys = [])
    {
        $this->sensitiveKeys = array_merge(self::DEFAULT_SENSITIVE_KEYS, $extraKeys);
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $this->maskArray($record->context);
        $extra = $this->maskArray($record->extra);

        return $record->with(context: $context, extra: $extra);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function maskArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($this->isSensitive((string) $key)) {
                $data[$key] = '***';
            } elseif (is_array($value)) {
                $data[$key] = $this->maskArray($value);
            }
        }

        return $data;
    }

    private function isSensitive(string $key): bool
    {
        $lower = strtolower($key);

        foreach ($this->sensitiveKeys as $sensitive) {
            if (str_contains($lower, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
