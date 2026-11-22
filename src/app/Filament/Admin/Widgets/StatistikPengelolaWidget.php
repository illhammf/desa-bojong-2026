<?php

namespace App\Filament\Admin\Widgets;

use App\Models\JenisLayanan;
use App\Models\Permohonan;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatistikPengelolaWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin']) ?? false;
    }

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $layananAktif = JenisLayanan::aktif()->count();
        $layananTotal = JenisLayanan::count();

        return [
            Stat::make('Total Pengguna', User::count())
                ->description('Seluruh akun terdaftar')
                ->color('gray'),
            Stat::make('Akun Masyarakat', User::role('masyarakat')->count())
                ->description('Warga pengguna layanan')
                ->color('primary'),
            Stat::make('Akun Petugas Desa', User::role('petugas')->count())
                ->description('Pemeriksa dan verifikator')
                ->color('info'),
            Stat::make('Akun Pengelola', User::role(['admin', 'super_admin'])->count())
                ->description('Administrator dan Super Admin')
                ->color('warning'),
            Stat::make('Layanan Aktif', $layananAktif)
                ->description("dari {$layananTotal} jenis layanan")
                ->color('success'),
            Stat::make('Total Permohonan', Permohonan::count())
                ->description('Tercatat pada sistem')
                ->color('gray'),
        ];
    }
}
