<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Role: string implements HasLabel
{
    case AdminPusat = 'admin_pusat';
    case AdminCabang = 'admin_cabang';
    case Viewer = 'viewer';

    public function getLabel(): string
    {
        return match ($this) {
            self::AdminPusat => 'Admin Pusat',
            self::AdminCabang => 'Admin Cabang',
            self::Viewer => 'Peninjau',
        };
    }
}
