<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\StatusPermohonan;
use App\Models\Permohonan;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatistikPetugasWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('petugas') ?? false;
    }

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $jumlah = Permohonan::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $keterangan = [
            'diajukan' => 'Menunggu diperiksa',
            'dalam_pemeriksaan' => 'Sedang diperiksa petugas',
            'perlu_perbaikan' => 'Menunggu perbaikan pemohon',
            'diproses' => 'Surat sedang disiapkan',
            'siap_diambil' => 'Menunggu diambil di balai desa',
        ];

        $stats = [
            Stat::make('Total Permohonan', (int) $jumlah->sum())
                ->description('Seluruh permohonan masuk')
                ->color('gray'),
        ];

        foreach (StatusPermohonan::cases() as $status) {
            $stats[] = Stat::make($status->getLabel(), (int) ($jumlah[$status->value] ?? 0))
                ->description($keterangan[$status->value])
                ->color($status->getColor());
        }

        return $stats;
    }
}
