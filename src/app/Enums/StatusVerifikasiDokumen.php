<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusVerifikasiDokumen: string implements HasLabel, HasColor
{
    case BelumDiperiksa = 'belum_diperiksa';
    case Sesuai = 'sesuai';
    case TidakSesuai = 'tidak_sesuai';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::BelumDiperiksa => 'Belum Diperiksa',
            self::Sesuai => 'Sesuai',
            self::TidakSesuai => 'Tidak Sesuai',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::BelumDiperiksa => 'gray',
            self::Sesuai => 'success',
            self::TidakSesuai => 'danger',
        };
    }
}
