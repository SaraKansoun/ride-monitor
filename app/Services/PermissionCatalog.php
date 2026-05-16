<?php

namespace App\Services;

final class PermissionCatalog
{
    public const VIEW_USERS = 'view users';

    public const MANAGE_USERS = 'manage users';

    public const VIEW_DRIVERS = 'view drivers';

    public const MANAGE_DRIVERS = 'manage drivers';

    public const VIEW_VEHICLES = 'view vehicles';

    public const MANAGE_VEHICLES = 'manage vehicles';

    public const VIEW_INCIDENTS = 'view incidents';

    public const CREATE_INCIDENTS = 'create incidents';

    public const VIEW_OWN_INCIDENTS = 'view own incidents';

    public const REVIEW_INCIDENTS = 'review incidents';

    public const VIEW_AI_ANALYSES = 'view ai analyses';

    public const VIEW_SAFETY_SCORES = 'view safety scores';

    public const VIEW_OWN_SAFETY_SCORE = 'view own safety score';

    public const MANAGE_DEACTIVATIONS = 'manage deactivations';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MONITOR = 'monitor';

    public const ROLE_DRIVER = 'driver';

    /**
     * @return list<string>
     */
    public static function permissions(): array
    {
        return [
            self::VIEW_USERS,
            self::MANAGE_USERS,
            self::VIEW_DRIVERS,
            self::MANAGE_DRIVERS,
            self::VIEW_VEHICLES,
            self::MANAGE_VEHICLES,
            self::VIEW_INCIDENTS,
            self::CREATE_INCIDENTS,
            self::VIEW_OWN_INCIDENTS,
            self::REVIEW_INCIDENTS,
            self::VIEW_AI_ANALYSES,
            self::VIEW_SAFETY_SCORES,
            self::VIEW_OWN_SAFETY_SCORE,
            self::MANAGE_DEACTIVATIONS,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rolePermissions(): array
    {
        return [
            self::ROLE_ADMIN => self::permissions(),
            self::ROLE_MONITOR => [
                self::VIEW_DRIVERS,
                self::VIEW_VEHICLES,
                self::VIEW_INCIDENTS,
                self::REVIEW_INCIDENTS,
                self::VIEW_AI_ANALYSES,
                self::VIEW_SAFETY_SCORES,
                self::MANAGE_DEACTIVATIONS,
            ],
            self::ROLE_DRIVER => [
                self::CREATE_INCIDENTS,
                self::VIEW_OWN_INCIDENTS,
                self::VIEW_OWN_SAFETY_SCORE,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function roles(): array
    {
        return array_keys(self::rolePermissions());
    }
}
