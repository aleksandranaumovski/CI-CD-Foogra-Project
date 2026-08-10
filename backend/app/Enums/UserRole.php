<?php

namespace App\Enums;

enum UserRole: string
{
    /** Full control over every resource in the system. */
    case Admin = 'admin';

    /** Owns one or more restaurants; may manage only their own. */
    case Owner = 'owner';

    /** Books tables, writes reviews, keeps a wishlist. */
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Owner => 'Restaurant Owner',
            self::Customer => 'Customer',
        };
    }

    /**
     * Roles that are allowed to reach the admin dashboard.
     *
     * @return array<int, self>
     */
    public static function staff(): array
    {
        return [self::Admin, self::Owner];
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
