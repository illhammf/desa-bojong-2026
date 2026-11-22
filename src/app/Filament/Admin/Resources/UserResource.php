<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use App\Services\PenggunaService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Kelola Akun';

    protected static ?string $modelLabel = 'Akun';

    protected static ?string $pluralModelLabel = 'Akun';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    /** BR-30: Administrator tidak melihat Administrator lain dan Super Admin. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('roles');
        $aktor = auth()->user();

        if ($aktor && ! $aktor->hasRole('super_admin')) {
            $query->where(function (Builder $q) use ($aktor) {
                $q->whereDoesntHave('roles', fn (Builder $r) => $r->whereIn('name', ['admin', 'super_admin']))
                    ->orWhere('users.id', $aktor->id);
            });
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Peran' => PenggunaService::LABEL[$record->roles->first()?->name] ?? '-',
            'Email' => $record->email,
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Data Akun')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->minLength(2)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->required()
                            ->email()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->prefixIcon('heroicon-m-envelope'),
                        Forms\Components\TextInput::make('no_hp')
                            ->label('Nomor HP / WhatsApp')
                            ->tel()
                            ->maxLength(15)
                            ->regex('/^[0-9+]+$/')
                            ->validationMessages(['regex' => 'Nomor hanya boleh berisi angka.']),
                        Forms\Components\Select::make('status_akun')
                            ->label('Status Akun')
                            ->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'])
                            ->default('aktif')
                            ->required()
                            ->native(false)
                            ->disabled(fn (?User $record): bool => $record?->id === auth()->id())
                            ->helperText('Akun nonaktif tidak dapat login.'),
                        Forms\Components\FileUpload::make('avatar_url')
                            ->label('Foto Profil')
                            ->image()
                            ->avatar()
                            ->directory('avatars')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Kata Sandi')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->label('Kata Sandi')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->confirmed()
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Kosongkan bila tidak ingin mengubah kata sandi.' : null),
                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('Konfirmasi Kata Sandi')
                            ->password()
                            ->revealable()
                            ->dehydrated(false)
                            ->required(fn (string $operation): bool => $operation === 'create'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Peran')
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->label('Peran')
                            ->required()
                            ->native(false)
                            ->live()
                            ->dehydrated(false)
                            ->options(function (?User $record): array {
                                $pilihan = PenggunaService::peranYangBolehDipilih(auth()->user());
                                $sekarang = $record?->roles->first()?->name;
                                if ($sekarang && ! in_array($sekarang, $pilihan, true)) {
                                    $pilihan[] = $sekarang;
                                }

                                return collect($pilihan)
                                    ->mapWithKeys(fn (string $r): array => [$r => PenggunaService::LABEL[$r]])
                                    ->all();
                            })
                            ->afterStateHydrated(fn (Forms\Components\Select $component, ?User $record) => $component->state($record?->roles->first()?->name))
                            ->disabled(fn (?User $record): bool => $record?->id === auth()->id())
                            ->helperText('Anda tidak dapat mengubah peran akun sendiri.'),
                    ]),

                Forms\Components\Section::make('Data Masyarakat')
                    ->schema([
                        Forms\Components\TextInput::make('nik')
                            ->label('NIK')
                            ->required()
                            ->length(16)
                            ->regex('/^[0-9]{16}$/')
                            ->validationMessages(['regex' => 'NIK harus 16 digit angka.'])
                            ->rules(fn (?User $record): array => [
                                Rule::unique('masyarakat', 'nik')->ignore($record?->id, 'user_id'),
                            ])
                            ->dehydrated(false)
                            ->afterStateHydrated(fn (Forms\Components\TextInput $component, ?User $record) => $component->state($record?->masyarakat?->nik)),
                        Forms\Components\Textarea::make('alamat')
                            ->label('Alamat')
                            ->rows(3)
                            ->dehydrated(false)
                            ->afterStateHydrated(fn (Forms\Components\Textarea $component, ?User $record) => $component->state($record?->masyarakat?->alamat)),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get): bool => $get('role') === 'masyarakat'),

                Forms\Components\Section::make('Data Kepegawaian')
                    ->schema([
                        Forms\Components\TextInput::make('nip')
                            ->label('NIP')
                            ->maxLength(30)
                            ->dehydrated(false)
                            ->afterStateHydrated(fn (Forms\Components\TextInput $component, ?User $record) => $component->state($record?->petugas?->nip ?? $record?->admin?->nip)),
                    ])
                    ->visible(fn (Get $get): bool => in_array($get('role'), ['petugas', 'admin'], true)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('no_hp')
                    ->label('No. HP')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => PenggunaService::LABEL[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'warning',
                        'petugas' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status_akun')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'aktif' ? 'Aktif' : 'Nonaktif')
                    ->color(fn (?string $state): string => $state === 'aktif' ? 'success' : 'gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('peran')
                    ->label('Peran')
                    ->options(PenggunaService::LABEL)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, string $peran) => $q->whereHas('roles', fn (Builder $r) => $r->where('name', $peran))
                    )),
                Tables\Filters\SelectFilter::make('status_akun')
                    ->label('Status')
                    ->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggleStatus')
                    ->label(fn (User $record): string => $record->isAktif() ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn (User $record): string => $record->isAktif() ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                    ->color(fn (User $record): string => $record->isAktif() ? 'danger' : 'success')
                    ->visible(fn (User $record): bool => $record->id !== auth()->id() && (auth()->user()?->can('update_user') ?? false))
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => $record->isAktif() ? 'Nonaktifkan akun?' : 'Aktifkan akun?')
                    ->modalDescription(fn (User $record): string => $record->isAktif()
                        ? 'Akun tidak dapat login sampai diaktifkan kembali. Data dan riwayat tetap tersimpan.'
                        : 'Akun dapat login kembali.')
                    ->action(function (User $record) {
                        if ($record->isAktif() && $alasan = PenggunaService::alasanTidakBolehNonaktif(auth()->user(), $record)) {
                            Notification::make()->danger()->title('Akun tidak dapat dinonaktifkan')->body($alasan)->send();

                            return;
                        }

                        $record->update(['status_akun' => $record->isAktif() ? 'nonaktif' : 'aktif']);
                    }),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (User $record): bool => $record->id === auth()->id())
                    ->before(fn ($action, User $record) => static::cegahHapus($action, $record)),
            ]);
    }

    public static function cegahHapus($action, User $record): void
    {
        if ($alasan = PenggunaService::alasanTidakBolehDihapus(auth()->user(), $record)) {
            Notification::make()
                ->danger()
                ->title('Akun tidak dapat dihapus')
                ->body($alasan)
                ->send();

            $action->cancel();
        }
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
