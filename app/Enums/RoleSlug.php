<?php

namespace App\Enums;

enum RoleSlug: string
{
    case DEVELOPER = 'developer';
    case SYSTEM_ADMIN = 'system_admin';
    case PERMITTER = 'permitter';
    case PIC = 'pic';
    case TEAM = 'team';
    case WAREHOUSE = 'warehouse';
    case KITCHEN = 'kitchen';
    case ADMIN_EVENT = 'admin_event';

    public function label(): string
    {
        return match ($this) {
            self::DEVELOPER => 'Developer',
            self::SYSTEM_ADMIN => 'Admin Sistem',
            self::PERMITTER => 'Permitter',
            self::PIC => 'PIC',
            self::TEAM => 'Tim Lapangan',
            self::WAREHOUSE => 'Gudang',
            self::KITCHEN => 'Kitchen',
            self::ADMIN_EVENT => 'Admin Event',
        };
    }
}
