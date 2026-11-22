<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TipePersyaratan: string implements HasLabel
{
    case Dokumen = 'dokumen';
    case Isian = 'isian';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Dokumen => 'Dokumen Pendukung',
            self::Isian => 'Isian Formulir',
        };
    }
}
