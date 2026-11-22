<?php

namespace App\Filament\Admin\Resources\PermohonanResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RiwayatRelationManager extends RelationManager
{
    protected static string $relationship = 'riwayat';

    protected static ?string $title = 'Riwayat Status';

    protected static ?string $modelLabel = 'riwayat';

    protected static ?string $pluralModelLabel = 'riwayat';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('status')
            ->modifyQueryUsing(fn (Builder $query) => $query->reorder()->orderByDesc('waktu_perubahan')->orderByDesc('id'))
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('waktu_perubahan')
                    ->label('Waktu')
                    ->dateTime('d M Y, H.i'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                Tables\Columns\TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->placeholder('-')
                    ->wrap(),
                Tables\Columns\TextColumn::make('petugas.user.name')
                    ->label('Oleh')
                    ->placeholder('Sistem'),
            ]);
    }
}
