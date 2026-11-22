<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PermohonanResource\Pages;
use App\Filament\Admin\Resources\PermohonanResource\RelationManagers\DokumenRelationManager;
use App\Filament\Admin\Resources\PermohonanResource\RelationManagers\RiwayatRelationManager;
use App\Models\Permohonan;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PermohonanResource extends Resource
{
    protected static ?string $model = Permohonan::class;

    protected static ?string $slug = 'permohonan';

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Pelayanan';

    protected static ?string $navigationLabel = 'Daftar Permohonan';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Permohonan';

    protected static ?string $pluralModelLabel = 'Permohonan';

    protected static ?string $recordTitleAttribute = 'nomor_permohonan';

    /**
     * Hanya Petugas yang menangani permohonan (BR-03, BR-05, BR-33).
     * Sementara berbasis role, nanti dialihkan ke permission Shield.
     */
    public static function bolehKelola(): bool
    {
        return auth()->user()?->hasRole('petugas') ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::bolehKelola();
    }

    public static function canView(Model $record): bool
    {
        return static::bolehKelola();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['pemohon', 'masyarakat', 'layanan']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal_pengajuan', 'desc')
            ->poll('60s')
            ->columns([
                Tables\Columns\TextColumn::make('nomor_permohonan')
                    ->label('Nomor Permohonan')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('pemohon.name')
                    ->label('Pemohon')
                    ->searchable(),
                Tables\Columns\TextColumn::make('masyarakat.nik')
                    ->label('NIK')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state): string => $state ? substr($state, 0, 6) . '******' . substr($state, -4) : '-'),
                Tables\Columns\TextColumn::make('layanan.nama_layanan')
                    ->label('Jenis Layanan')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('tanggal_pengajuan')
                    ->label('Tanggal Pengajuan')
                    ->dateTime('d M Y, H.i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('jenis_layanan_id')
                    ->label('Jenis Layanan')
                    ->relationship('layanan', 'nama_layanan')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('tanggal')
                    ->label('Tanggal Pengajuan')
                    ->form([
                        Forms\Components\DatePicker::make('dari')->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('sampai')->label('Sampai Tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, $d) => $q->whereDate('tanggal_pengajuan', '>=', $d))
                        ->when($data['sampai'] ?? null, fn (Builder $q, $d) => $q->whereDate('tanggal_pengajuan', '<=', $d))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Periksa')
                    ->icon('heroicon-o-clipboard-document-check'),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informasi Permohonan')
                    ->schema([
                        Infolists\Components\TextEntry::make('nomor_permohonan')
                            ->label('Nomor Permohonan')
                            ->copyable()
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                        Infolists\Components\TextEntry::make('layanan.nama_layanan')
                            ->label('Jenis Layanan'),
                        Infolists\Components\TextEntry::make('tanggal_pengajuan')
                            ->label('Tanggal Pengajuan')
                            ->dateTime('d F Y, H.i')
                            ->suffix(' WIB'),
                        Infolists\Components\TextEntry::make('alasan_pengajuan')
                            ->label('Alasan Pengajuan')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('catatan_petugas')
                            ->label('Catatan Perbaikan Terakhir')
                            ->color('warning')
                            ->visible(fn (Permohonan $record): bool => filled($record->catatan_petugas))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Data Pemohon')
                    ->schema([
                        Infolists\Components\TextEntry::make('pemohon.name')->label('Nama Lengkap'),
                        Infolists\Components\TextEntry::make('masyarakat.nik')->label('NIK')->copyable(),
                        Infolists\Components\TextEntry::make('pemohon.no_hp')->label('Nomor WhatsApp / HP')->placeholder('-'),
                        Infolists\Components\TextEntry::make('pemohon.email')->label('Email'),
                        Infolists\Components\TextEntry::make('masyarakat.alamat')->label('Alamat')->placeholder('-')->columnSpanFull(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Data Isian Formulir')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('isian')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('persyaratan.nama_persyaratan')->label('Isian'),
                                Infolists\Components\TextEntry::make('nilai_isian')->label('Jawaban')->placeholder('-'),
                            ])
                            ->columns(2),
                    ])
                    ->visible(fn (Permohonan $record): bool => $record->isian->isNotEmpty()),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            DokumenRelationManager::class,
            RiwayatRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermohonan::route('/'),
            'view' => Pages\ViewPermohonan::route('/{record}'),
        ];
    }
}
