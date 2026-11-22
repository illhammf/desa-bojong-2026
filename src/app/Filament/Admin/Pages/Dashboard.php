<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\PermohonanTerbaruWidget;
use App\Filament\Admin\Widgets\StatistikPengelolaWidget;
use App\Filament\Admin\Widgets\StatistikPetugasWidget;
use App\Filament\Admin\Widgets\StatusPermohonanChart;
use App\Filament\Admin\Widgets\VolumeLayananChart;
use App\Services\PenggunaService;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dashboard';

    public function getSubheading(): ?string
    {
        $user = auth()->user();
        $peran = PenggunaService::LABEL[$user?->roles->first()?->name] ?? null;

        return $peran ? "Selamat datang, {$user->name} ({$peran})." : null;
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }

    /** Daftar widget eksplisit; tiap widget menyaring tampilannya lewat canView() sesuai peran. */
    public function getWidgets(): array
    {
        return [
            StatistikPetugasWidget::class,
            StatistikPengelolaWidget::class,
            StatusPermohonanChart::class,
            VolumeLayananChart::class,
            PermohonanTerbaruWidget::class,
        ];
    }
}
