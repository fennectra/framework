<?php

namespace Fennec\Core\Profiler;

class ProfileEntry implements \JsonSerializable
{
    public string $id;
    public string $method;
    public string $uri;
    public float $startTime;
    public ?float $endTime = null;
    public ?float $durationMs = null;
    public int $memoryStart;
    public ?int $memoryEnd = null;
    public ?int $peakMemory = null;
    public ?int $statusCode = null;

    /** @var array<array{sql: string, params: array, ms: float}> */
    public array $queries = [];

    /** @var array<array{name: string, ms: float}> */
    public array $events = [];

    /** @var array<array{class: string, ms: float}> */
    public array $middlewares = [];

    /** @var string[] */
    public array $resolutions = [];

    /** @var string[] */
    public array $n1Warnings = [];

    public ?string $route = null;

    public function __construct(string $method, string $uri)
    {
        $this->id = bin2hex(random_bytes(8));
        $this->method = $method;
        $this->uri = $uri;
        $this->startTime = microtime(true);
        $this->memoryStart = memory_get_usage(true);
    }

    public function stop(): void
    {
        $this->endTime = microtime(true);
        $this->durationMs = round(($this->endTime - $this->startTime) * 1000, 2);
        $this->memoryEnd = memory_get_usage(true);
        $this->peakMemory = memory_get_peak_usage(true);
        $this->statusCode = http_response_code() ?: 200;
    }

    public function jsonSerialize(): array
    {
        $totalQueryMs = array_sum(array_column($this->queries, 'ms'));

        return [
            'id' => $this->id,
            'method' => $this->method,
            'uri' => $this->uri,
            'status' => $this->statusCode,
            'duration_ms' => $this->durationMs,
            'memory_peak_mb' => $this->peakMemory ? round($this->peakMemory / 1048576, 2) : null,
            'route' => $this->route,
            'db' => [
                'query_count' => count($this->queries),
                'total_ms' => round($totalQueryMs, 2),
                'queries' => $this->queries,
            ],
            'events' => $this->events,
            'middleware' => $this->middlewares,
            'resolutions' => count($this->resolutions),
            'n1_warnings' => $this->n1Warnings,
            'timestamp' => date('c'),
        ];
    }
}
