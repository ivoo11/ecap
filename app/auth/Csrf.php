<?php

declare(strict_types=1);

require_once __DIR__ . '/Session.php';

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        Session::start();

        if (
            !isset($_SESSION[self::SESSION_KEY])
            || !is_string($_SESSION[self::SESSION_KEY])
            || $_SESSION[self::SESSION_KEY] === ''
        ) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
            . '">';
    }

    public static function validate(?string $token): bool
    {
        Session::start();

        $storedToken = $_SESSION[self::SESSION_KEY] ?? null;

        if (
            !is_string($storedToken)
            || $storedToken === ''
            || !is_string($token)
            || $token === ''
        ) {
            return false;
        }

        return hash_equals($storedToken, $token);
    }

    public static function regenerate(): void
    {
        Session::start();
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }
}