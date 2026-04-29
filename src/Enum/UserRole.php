<?php

namespace App\Enum;

enum UserRole: string
{
    case Student = 'student';
    case Owner = 'owner';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::Student => 'Student',
            self::Owner => 'Owner',
            self::Admin => 'Admin',
        };
    }
}
