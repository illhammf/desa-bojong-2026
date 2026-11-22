<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\StatusPermohonan;
use App\Models\Permohonan;
use Filament\Widgets\ChartWidget;

class StatusPermohonanChart extends ChartWidget
{
    protected static ?string $heading = 'Status Permohonan';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin']) ?? false;
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $jumlah = Permohonan::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $warna = [
            'diajukan' => '#9ca3af',
            'dalam_pemeriksaan' => '#38bdf8',
            'perlu_perbaikan' => '#f59e0b',
            'diproses' => '#3b82f6',
            'siap_diambil' => '#22c55e',
        ];

        $labels = [];
        $data = [];
        $background = [];

        foreach (StatusPermohonan::cases() as $status) {
            $labels[] = $status->getLabel();
            $data[] = (int) ($jumlah[$status->value] ?? 0);
            $background[] = $warna[$status->value];
        }

        return [
            'datasets' => [[
                'label' => 'Permohonan',
                'data' => $data,
                'backgroundColor' => $background,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
        ];
    }
}
