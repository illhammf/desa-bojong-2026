<?php

namespace App\Filament\Admin\Resources\PermohonanResource\Pages;

use App\Enums\StatusPermohonan;
use App\Filament\Admin\Resources\PermohonanResource;
use App\Models\Permohonan;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPermohonan extends ListRecords
{
    protected static string $resource = PermohonanResource::class;

    protected static ?string $title = 'Daftar Permohonan';

    public function getTabs(): array
    {
        $tabs = [
            'semua' => Tab::make('Semua')->badge(Permohonan::count()),
        ];

        foreach (StatusPermohonan::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->badge(Permohonan::where('status', $status->value)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status->value));
        }

        return $tabs;
    }
}
