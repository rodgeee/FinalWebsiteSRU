<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Reads Google OAuth env at runtime (not from compiled container args).
 * Needed because PHP-FPM may not inherit host env and entrypoint rebuilds /app/.env.
 */
final class GoogleOAuthSettings
{
    /** @var array<string, string>|null */
    private ?array $dotenvCache = null;

    public function clientId(): string
    {
        return $this->read('GOOGLE_CLIENT_ID');
    }

    public function clientSecret(): string
    {
        return $this->read('GOOGLE_CLIENT_SECRET');
    }

    public function callbackUrl(): string
    {
        return $this->read('GOOGLE_CALLBACK_URL');
    }

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    private function read(string $name): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return $this->readFromProjectDotenv($name);
    }

    private function readFromProjectDotenv(string $name): string
    {
        if ($this->dotenvCache === null) {
            $this->dotenvCache = [];
            $path = dirname(__DIR__, 2).'/.env';
            if (!is_readable($path)) {
                return '';
            }
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $raw] = explode('=', $line, 2);
                $this->dotenvCache[trim($key)] = trim($raw, " \t\"'");
            }
        }

        return $this->dotenvCache[$name] ?? '';
    }
}
