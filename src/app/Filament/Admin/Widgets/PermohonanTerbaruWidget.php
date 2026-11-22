<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\PermohonanResource;
use App\Models\Permohonan;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PermohonanTerbaruWidget extends BaseWidget
{
    protected static ?string $heading = 'Permohonan Terbaru';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['petugas', 'admin', 'super_admin']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Permohonan::query()
                    ->with(['pemohon', 'layanan'])
                    ->latest('tanggal_pengajuan')
                    ->limit(5)
            )
            ->description(fn (): ?string => PermohonanResource::bolehKelola()
                ? null
                : 'Hanya untuk pemantauan. Verifikasi dan pembaruan status dilakukan oleh Petugas Desa.')
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('nomor_permohonan')
                    ->label('Nomor Permohonan')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('pemohon.name')
                    ->label('Pemohon'),
                Tables\Columns\TextColumn::make('layanan.nama_layanan')
                    ->label('Jenis Layanan')
                    ->wrap(),
                Tables\Columns\TextColumn::make('tanggal_pengajuan')
                    ->label('Tanggal Pengajuan')
                    ->dateTime('d M Y, H.i'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->actions([
                Tables\Actions\Action::make('periksa')
                    ->label('Periksa')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->url(fn (Permohonan $record): string => PermohonanResource::getUrl('view', ['record' => $record]))
                    ->visible(fn (): bool => PermohonanResource::bolehKelola()),
            ]);
    }
}
