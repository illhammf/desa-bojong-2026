<?php

namespace App\Filament\Admin\Widgets;

use App\Models\JenisLayanan;
use Filament\Widgets\ChartWidget;

class VolumeLayananChart extends ChartWidget
{
    protected static ?string $heading = 'Volume Permohonan per Layanan';

    protected static ?string $description = 'Sepuluh layanan dengan permohonan terbanyak.';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin']) ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $baris = JenisLayanan::query()
            ->withCount('permohonan')
            ->having('permohonan_count', '>', 0)
            ->orderByDesc('permohonan_count')
            ->limit(10)
            ->get();

        return [
            'datasets' => [[
                'label' => 'Jumlah permohonan',
                'data' => $baris->pluck('permohonan_count')->all(),
                'backgroundColor' => '#3b82f6',
            ]],
            'labels' => $baris->pluck('nama_layanan')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['x' => ['ticks' => ['precision' => 0]]],
        ];
    }
}
