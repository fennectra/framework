<?php

namespace Fennec\Core\Queue;

interface JobInterface
{
    /**
     * Execute le job avec le payload donne.
     */
    public function handle(array $payload): void;

    /**
     * Nombre maximum de tentatives.
     */
    public function retries(): int;

    /**
     * Delai en secondes avant une nouvelle tentative.
     */
    public function retryDelay(): int;

    /**
     * Appele quand le job echoue definitivement.
     */
    public function failed(array $payload, \Throwable $e): void;
}
