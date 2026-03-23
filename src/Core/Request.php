<?php

namespace Fennec\Core;

class Request
{
    private array $attributes = [];
    private ?array $parsedBody = null;

    public function __construct(
        private string $method = '',
        private string $uri = '',
        private array $server = [],
        private array $query = [],
    ) {
        if (empty($this->method)) {
            $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        }
        if (empty($this->uri)) {
            $this->uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        }
        if (empty($this->server)) {
            $this->server = $_SERVER;
        }
        if (empty($this->query)) {
            $this->query = $_GET;
        }
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getHeader(string $name): ?string
    {
        // Convertir le nom en format $_SERVER (HTTP_X_CUSTOM_HEADER)
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return $this->server[$key] ?? null;
    }

    public function getBody(): ?array
    {
        if ($this->parsedBody === null) {
            $raw = file_get_contents('php://input');
            $this->parsedBody = $raw ? json_decode($raw, true) : null;
        }

        return $this->parsedBody;
    }

    public function getQuery(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * Retourne une copie avec un attribut ajouté.
     */
    public function withAttribute(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;

        return $clone;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getServer(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->server;
        }

        return $this->server[$key] ?? $default;
    }
}
