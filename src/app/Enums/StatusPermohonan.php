<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusPermohonan: string implements HasLabel, HasColor
{
    case Diajukan = 'diajukan';
    case DalamPemeriksaan = 'dalam_pemeriksaan';
    case PerluPerbaikan = 'perlu_perbaikan';
    case Diproses = 'diproses';
    case SiapDiambil = 'siap_diambil';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Diajukan => 'Diajukan',
            self::DalamPemeriksaan => 'Dalam Pemeriksaan',
            self::PerluPerbaikan => 'Perlu Perbaikan',
            self::Diproses => 'Diproses',
            self::SiapDiambil => 'Siap Diambil',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Diajukan => 'gray',
            self::DalamPemeriksaan => 'info',
            self::PerluPerbaikan => 'warning',
            self::Diproses => 'primary',
            self::SiapDiambil => 'success',
        };
    }

    /** Status tujuan yang diizinkan dari status ini (PRD 7.7, FR-41). */
    public function transisi(): array
    {
        return match ($this) {
            self::Diajukan => [self::DalamPemeriksaan],
            self::DalamPemeriksaan => [self::PerluPerbaikan, self::Diproses],
            self::PerluPerbaikan => [self::DalamPemeriksaan],
            self::Diproses => [self::SiapDiambil],
            self::SiapDiambil => [],
        };
    }

    public function bolehKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->transisi(), true);
    }
}
