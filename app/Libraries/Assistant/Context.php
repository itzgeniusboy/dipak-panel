<?php

namespace App\Libraries\Assistant;

/**
 * Holds the authenticated identity for the current request.
 *
 * The API filter puts the user (and the key row) here; controllers read it.
 * A static holder avoids mutating CodeIgniter's request object with dynamic
 * properties, which deprecates on PHP 8.2+.
 */
class Context
{
    private static ?object $user = null;
    private static ?object $key  = null;

    public static function set(object $user, ?object $key = null): void
    {
        self::$user = $user;
        self::$key  = $key;
    }

    public static function user(): ?object
    {
        return self::$user;
    }

    public static function key(): ?object
    {
        return self::$key;
    }

    public static function clear(): void
    {
        self::$user = null;
        self::$key  = null;
    }

    public static function userId(): int
    {
        $user = self::$user;

        return $user ? (int) $user->id_users : 0;
    }
}