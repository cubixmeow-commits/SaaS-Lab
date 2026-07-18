<?php

declare(strict_types=1);

/**
 * Thin CSRF facade — helpers remain the public API.
 */
final class Csrf
{
    public static function token(): string
    {
        return csrf_token();
    }

    public static function field(): string
    {
        return csrf_field();
    }

    public static function verifyOrFail(): void
    {
        verify_csrf_or_fail();
    }
}
