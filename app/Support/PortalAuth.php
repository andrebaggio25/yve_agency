<?php

declare(strict_types=1);

namespace App\Support;

class PortalAuth
{
    private static ?array $client = null;

    public static function set(array $client): void
    {
        self::$client = $client;
    }

    public static function client(): ?array
    {
        return self::$client;
    }

    public static function clientId(): ?int
    {
        return isset(self::$client['id']) ? (int) self::$client['id'] : null;
    }

    public static function agencyId(): ?int
    {
        return isset(self::$client['agency_id']) ? (int) self::$client['agency_id'] : null;
    }

    public static function token(): ?string
    {
        return self::$client['portal_token'] ?? null;
    }
}
