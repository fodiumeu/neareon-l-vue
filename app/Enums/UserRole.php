<?php

namespace App\Enums;

enum UserRole: string
{
    case Member = 'member';
    case Moderator = 'moderator';
    case Admin = 'admin';
    case Owner = 'owner';

    public function level(): int
    {
        return match ($this) {
            self::Member => 10,
            self::Moderator => 20,
            self::Admin => 30,
            self::Owner => 40,
        };
    }
}
