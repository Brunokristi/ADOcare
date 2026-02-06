<?php

namespace App\Enums;

enum RoleScope: string
{
    case BRANCH = 'branch';
    case COMPANY = 'company';
    case GLOBAL = 'global';

    public static function values(): array
    {
        return array_map(fn(self $c) => $c->value, self::cases());
    }
}
