<?php

declare(strict_types=1);

namespace Wordpresso\Core;

/**
 * HTTP response value object.
 */
final class Response
{
    private array $headers = [];
    private int $status;

    public function __construct(
        private string $body = '',
        int $status = 200,
        array $headers = [],
    ) {
        $this->status = $status;
        $this->headers = $headers;
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public static function error(string $message, int $status = 400, array $extra = []): self
    {
        return self::json(array_merge(['success' => false, 'error' => $message], $extra), $status);
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function redirect(string $location): self
    {
        return new self('', 302, ['Location' => $location]);
    }

    /** Stream a file download and terminate. */
    public static function file(string $path, string $downloadName, string $contentType = 'application/zip'): self
    {
        $size = filesize($path);
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return self::error('Package file is unreadable.', 500);
        }
        $body = stream_get_contents($fh) ?: '';
        fclose($fh);
        return new self($body, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . addslashes($downloadName) . '"',
            'Content-Length' => (string) $size,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
            // Baseline security headers for HTML responses.
            header('X-Frame-Options: DENY');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('X-Content-Type-Options: nosniff');
        }
        echo $this->body;
    }
}
