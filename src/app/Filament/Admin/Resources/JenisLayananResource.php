<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\JenisLayananResource\Pages;
use App\Filament\Admin\Resources\JenisLayananResource\RelationManagers\PersyaratanRelationManager;
use App\Models\JenisLayanan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class JenisLayananResource extends Resource
{
    protected static ?string $model = JenisLayanan::class;

    protected static ?string $slug = 'jenis-layanan';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Jenis Layanan';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Jenis Layanan';

    protected static ?string $pluralModelLabel = 'Jenis Layanan';

    protected static ?string $recordTitleAttribute = 'nama_layanan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Layanan')
                    ->schema([
                        Forms\Components\TextInput::make('kode')
                            ->label('Kode Layanan')
                            ->required()
                            ->maxLength(10)
                            ->regex('/^[A-Z0-9]+$/')
                            ->validationMessages([
                                'regex' => 'Kode hanya boleh huruf kapital dan angka, tanpa spasi.',
                            ])
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('kode', strtoupper((string) $state)))
                            ->disabled(fn (?JenisLayanan $record): bool => $record?->permohonan()->exists() ?? false)
                            ->helperText('Dipakai pada nomor pengajuan (REG-KODE-TAHUN-URUT). Terkunci setelah layanan memiliki permohonan.'),
                        Forms\Components\TextInput::make('nama_layanan')
                            ->label('Nama Layanan')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Textarea::make('deskripsi')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('estimasi_waktu')
                            ->label('Estimasi Waktu Penyelesaian')
                            ->placeholder('Contoh: 1 Hari Kerja')
                            ->maxLength(50),
                        Forms\Components\Toggle::make('aktif')
                            ->label('Layanan Aktif')
                            ->default(true)
                            ->visibleOn('create')
                            ->helperText('Untuk mengubah status layanan yang sudah ada, gunakan tombol Aktifkan/Nonaktifkan di daftar layanan.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                Tables\Columns\TextColumn::make('kode')
                    ->label('Kode')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nama_layanan')
                    ->label('Nama Layanan')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('estimasi_waktu')
                    ->label('Estimasi')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('persyaratan_count')
                    ->label('Persyaratan')
                    ->counts('persyaratan')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('permohonan_count')
                    ->label('Permohonan')
                    ->counts('permohonan')
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('aktif')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('aktif')
                    ->label('Status Layanan')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggleAktif')
                    ->label(fn (JenisLayanan $record): string => $record->aktif ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn (JenisLayanan $record): string => $record->aktif ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                    ->color(fn (JenisLayanan $record): string => $record->aktif ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (JenisLayanan $record): string => $record->aktif ? 'Nonaktifkan layanan?' : 'Aktifkan layanan?')
                    ->modalDescription(fn (JenisLayanan $record): string => $record->aktif
                        ? 'Layanan tidak akan tampil dan tidak dapat diajukan. Permohonan yang sudah ada tetap tersimpan.'
                        : 'Layanan akan tampil dan dapat diajukan oleh masyarakat.')
                    ->action(fn (JenisLayanan $record) => $record->update(['aktif' => ! $record->aktif])),
                Tables\Actions\DeleteAction::make()
                    ->before(fn ($action, JenisLayanan $record) => static::cegahHapusJikaDipakai($action, $record)),
            ]);
    }

    /** Layanan yang sudah punya permohonan tidak boleh dihapus (PRD 7.11). */
    public static function cegahHapusJikaDipakai($action, JenisLayanan $record): void
    {
        if ($record->permohonan()->exists()) {
            Notification::make()
                ->danger()
                ->title('Layanan tidak dapat dihapus')
                ->body('Layanan ini sudah memiliki permohonan. Nonaktifkan saja agar tidak tampil untuk pengajuan baru.')
                ->send();

            $action->cancel();
        }
    }

    public static function getRelations(): array
    {
        return [
            PersyaratanRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJenisLayanans::route('/'),
            'create' => Pages\CreateJenisLayanan::route('/create'),
            'edit' => Pages\EditJenisLayanan::route('/{record}/edit'),
        ];
    }
}
