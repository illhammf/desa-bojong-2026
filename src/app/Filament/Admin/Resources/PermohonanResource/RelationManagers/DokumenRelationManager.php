<?php

namespace App\Filament\Admin\Resources\PermohonanResource\RelationManagers;

use App\Enums\StatusPermohonan;
use App\Enums\StatusVerifikasiDokumen;
use App\Models\Dokumen;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DokumenRelationManager extends RelationManager
{
    protected static string $relationship = 'dokumen';

    protected static ?string $title = 'Dokumen Pendukung';

    protected static ?string $modelLabel = 'dokumen';

    protected static ?string $pluralModelLabel = 'dokumen';

    /** Aksi verifikasi harus aktif di halaman detail. */
    public function isReadOnly(): bool
    {
        return false;
    }

    private function bisaMemeriksa(): bool
    {
        return (auth()->user()?->hasRole('petugas') ?? false)
            && $this->getOwnerRecord()->status === StatusPermohonan::DalamPemeriksaan;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_file')
            ->emptyStateHeading('Tidak ada dokumen')
            ->emptyStateDescription('Layanan ini tidak mensyaratkan dokumen, atau pemohon belum mengunggah.')
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('persyaratan.nama_persyaratan')
                    ->label('Persyaratan')
                    ->placeholder('Dokumen tambahan')
                    ->wrap(),
                Tables\Columns\TextColumn::make('nama_file')
                    ->label('Nama Berkas'),
                Tables\Columns\TextColumn::make('ukuran_file')
                    ->label('Ukuran')
                    ->formatStateUsing(fn ($state): string => (int) $state >= 1048576
                        ? round($state / 1048576, 1) . ' MB'
                        : max(1, (int) round($state / 1024)) . ' KB'),
                Tables\Columns\TextColumn::make('tanggal_upload')
                    ->label('Diunggah')
                    ->dateTime('d M Y, H.i'),
                Tables\Columns\TextColumn::make('status_verifikasi')
                    ->label('Hasil Verifikasi')
                    ->badge(),
            ])
            ->actions([
                Tables\Actions\Action::make('lihat')
                    ->label('Lihat')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Dokumen $record): string => route('dokumen.tampil', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('sesuai')
                    ->label('Sesuai')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Dokumen $record): bool => $this->bisaMemeriksa() && $record->status_verifikasi !== StatusVerifikasiDokumen::Sesuai)
                    ->action(fn (Dokumen $record) => $record->update(['status_verifikasi' => StatusVerifikasiDokumen::Sesuai])),
                Tables\Actions\Action::make('tidakSesuai')
                    ->label('Tidak Sesuai')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Dokumen $record): bool => $this->bisaMemeriksa() && $record->status_verifikasi !== StatusVerifikasiDokumen::TidakSesuai)
                    ->action(fn (Dokumen $record) => $record->update(['status_verifikasi' => StatusVerifikasiDokumen::TidakSesuai])),
            ]);
    }
}
