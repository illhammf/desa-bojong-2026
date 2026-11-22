<?php

namespace App\Filament\Admin\Resources\JenisLayananResource\RelationManagers;

use App\Enums\TipePersyaratan;
use App\Models\Persyaratan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PersyaratanRelationManager extends RelationManager
{
    protected static string $relationship = 'persyaratan';

    protected static ?string $title = 'Persyaratan';

    protected static ?string $modelLabel = 'persyaratan';

    protected static ?string $pluralModelLabel = 'persyaratan';

    protected static ?string $recordTitleAttribute = 'nama_persyaratan';

    public function form(Form $form): Form
    {
        $isIsian = fn (Get $get): bool => in_array($get('tipe_persyaratan'), ['isian', TipePersyaratan::Isian], true);

        return $form
            ->schema([
                Forms\Components\TextInput::make('nama_persyaratan')
                    ->label('Nama Persyaratan')
                    ->required()
                    ->maxLength(150)
                    ->columnSpanFull(),
                Forms\Components\Select::make('tipe_persyaratan')
                    ->label('Jenis Persyaratan')
                    ->options(TipePersyaratan::class)
                    ->default(TipePersyaratan::Dokumen->value)
                    ->required()
                    ->native(false)
                    ->live(),
                Forms\Components\Select::make('tipe_input')
                    ->label('Bentuk Isian')
                    ->options([
                        'teks' => 'Teks singkat',
                        'area_teks' => 'Teks panjang',
                        'angka' => 'Angka',
                        'tanggal' => 'Tanggal',
                        'pilihan' => 'Pilihan',
                    ])
                    ->default('teks')
                    ->required()
                    ->native(false)
                    ->live()
                    ->visible($isIsian),
                Forms\Components\TagsInput::make('opsi')
                    ->label('Daftar Pilihan')
                    ->placeholder('Ketik pilihan lalu tekan Enter')
                    ->helperText('Contoh: Pindah, Upgrade.')
                    ->required()
                    ->visible(fn (Get $get): bool => $isIsian($get) && $get('tipe_input') === 'pilihan')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('wajib')
                    ->label('Wajib')
                    ->default(true)
                    ->helperText('Wajib dipenuhi sebelum permohonan dapat dikirim.'),
                Forms\Components\Toggle::make('aktif')
                    ->label('Aktif')
                    ->default(true)
                    ->helperText('Persyaratan nonaktif tidak tampil pada formulir.'),
                Forms\Components\Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_persyaratan')
            ->reorderable('urutan')
            ->defaultSort('urutan')
            ->columns([
                Tables\Columns\TextColumn::make('nama_persyaratan')
                    ->label('Nama Persyaratan')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('tipe_persyaratan')
                    ->label('Jenis')
                    ->badge(),
                Tables\Columns\TextColumn::make('tipe_input')
                    ->label('Bentuk Isian')
                    ->formatStateUsing(fn ($state, Persyaratan $record): string => $record->tipe_persyaratan === TipePersyaratan::Isian
                        ? ucfirst(str_replace('_', ' ', (string) $state))
                        : '-'),
                Tables\Columns\IconColumn::make('wajib')
                    ->label('Wajib')
                    ->boolean(),
                Tables\Columns\IconColumn::make('aktif')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipe_persyaratan')
                    ->label('Jenis')
                    ->options(TipePersyaratan::class),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Tambah Persyaratan'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($action, Persyaratan $record) {
                        if ($record->isianPermohonan()->exists() || $record->dokumen()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Persyaratan tidak dapat dihapus')
                                ->body('Persyaratan ini sudah dipakai pada permohonan. Nonaktifkan saja agar tidak tampil pada formulir.')
                                ->send();

                            $action->cancel();
                        }
                    }),
            ]);
    }
}
