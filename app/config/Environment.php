<?php
declare(strict_types=1);

final class Environment
{
    public static function isLocal(): bool
    {
        $environment = getenv('ECAP_ENV');
        if ($environment !== false && !in_array($environment, ['', 'local', 'production'], true)) {
            throw new RuntimeException('ECAP_ENV no válido.');
        }
        // El servidor de desarrollo y esta Mac nunca utilizan producción.
        return $environment === 'local'
            || PHP_SAPI === 'cli-server'
            || PHP_OS_FAMILY === 'Darwin'
            || in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true);
    }

    public static function requireLocal(): void
    {
        if (getenv('ECAP_ENV') !== 'local' || !self::isLocal()) {
            throw new RuntimeException('Esta operación exige ECAP_ENV=local.');
        }
    }

    public static function runtimePath(): string
    {
        return dirname(__DIR__, 3) . '/ecap_local';
    }
}
