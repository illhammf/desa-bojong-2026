<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\JenisLayananResource\Pages;
use App\Filament\Admin\Resources\JenisLayananResource\RelationManagers;
use App\Models\JenisLayanan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class JenisLayananResource extends Resource
{
    protected static ?string $model = JenisLayanan::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('kode')
                    ->required()
                    ->maxLength(10),
                Forms\Components\TextInput::make('nama_layanan')
                    ->required()
                    ->maxLength(100),
                Forms\Components\Textarea::make('deskripsi')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('estimasi_waktu')
                    ->maxLength(50)
                    ->default(null),
                Forms\Components\Toggle::make('aktif')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kode')
                    ->searchable(),
                Tables\Columns\TextColumn::make('nama_layanan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('estimasi_waktu')
                    ->searchable(),
                Tables\Columns\IconColumn::make('aktif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
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
