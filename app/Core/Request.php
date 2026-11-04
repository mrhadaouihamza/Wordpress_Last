<?php

declare(strict_types=1);

namespace Wordpresso\Core;

/**
 * HTTP request abstraction over PHP superglobals.
 */
final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $headers,
        public readonly string $rawBody,
        public readonly string $ip,
    ) {
    }

    public static function capture(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        // Support method override for HTML forms (DELETE etc.).
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $raw = file_get_contents('php://input') ?: '';
        $post = $_POST;
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper((string) $_POST['_method']);
        }
        return new self(
            strtoupper($method),
            $path,
            $_GET,
            $post,
            $headers,
            $raw,
            self::clientIp(),
        );
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if ($this->isJson()) {
            $json = json_decode($this->rawBody, true);
            if (is_array($json) && array_key_exists($key, $json)) {
                return $json[$key];
            }
        }
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** POST field accessor (form-encoded bodies). */
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function isJson(): bool
    {
        return str_contains((string) $this->header('content-type', ''), 'json');
    }

    private static function clientIp(): string
    {
        // REMOTE_ADDR only — never trust forwarded headers without a trusted
        // proxy configuration (documented in docs/DEPLOYMENT.md).
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
