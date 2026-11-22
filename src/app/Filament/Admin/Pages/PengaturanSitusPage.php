<?php

namespace App\Filament\Admin\Pages;

use App\Models\PengaturanSitus;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PengaturanSitusPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Pengaturan Situs';

    protected static ?string $title = 'Pengaturan Situs';

    protected static ?string $slug = 'pengaturan-situs';

    protected static string $view = 'filament.admin.pages.pengaturan-situs';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** Sementara berbasis role. Nanti dialihkan ke permission Shield. */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin']) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(PengaturanSitus::ambil()->attributesToArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identitas Situs')
                    ->description('Tampil pada header, halaman login, dan judul panel.')
                    ->schema([
                        Forms\Components\TextInput::make('nama_situs')
                            ->label('Nama Situs')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('tagline')
                            ->label('Tagline')
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('logo')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('situs')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->helperText('Maksimal 2 MB. Dipakai juga sebagai ikon tab browser.'),
                        Forms\Components\FileUpload::make('foto_beranda')
                            ->label('Foto Beranda')
                            ->image()
                            ->disk('public')
                            ->directory('situs')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->helperText('Foto kantor balai desa pada bagian atas beranda.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Instansi dan Kontak')
                    ->schema([
                        Forms\Components\TextInput::make('nama_instansi')
                            ->label('Nama Instansi')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('kode_pos')
                            ->label('Kode Pos')
                            ->maxLength(10),
                        Forms\Components\Textarea::make('alamat_kantor')
                            ->label('Alamat Kantor')
                            ->rows(2)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('telepon')
                            ->label('Telepon')
                            ->maxLength(30),
                        Forms\Components\TextInput::make('whatsapp')
                            ->label('WhatsApp')
                            ->maxLength(30),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('jam_layanan')
                            ->label('Jam Layanan')
                            ->rows(4)
                            ->helperText('Satu baris untuk setiap hari atau kelompok hari.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Konten Beranda')
                    ->schema([
                        Forms\Components\TextInput::make('judul_hero')
                            ->label('Judul Utama')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('deskripsi_hero')
                            ->label('Deskripsi Utama')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('pengumuman')
                            ->label('Pengumuman Pelayanan')
                            ->rows(2)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('judul_informasi')
                            ->label('Judul Informasi Penting')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('isi_informasi')
                            ->label('Isi Informasi Penting')
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\Repeater::make('langkah_alur')
                            ->label('Langkah Alur Pelayanan')
                            ->schema([
                                Forms\Components\TextInput::make('judul')
                                    ->label('Judul Langkah')
                                    ->required()
                                    ->maxLength(100),
                                Forms\Components\Textarea::make('deskripsi')
                                    ->label('Deskripsi')
                                    ->required()
                                    ->rows(2),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['judul'] ?? null)
                            ->collapsible()
                            ->reorderable()
                            ->minItems(1)
                            ->maxItems(6)
                            ->addActionLabel('Tambah langkah')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('catatan_pengambilan')
                            ->label('Catatan Pengambilan Surat')
                            ->rows(3)
                            ->helperText('Tampil pada permohonan berstatus Siap Diambil.')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('teks_footer')
                            ->label('Teks Footer')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $pengaturan = PengaturanSitus::query()->first() ?? new PengaturanSitus();
        $pengaturan->fill($data)->save();
        PengaturanSitus::lupakan();

        Notification::make()
            ->success()
            ->title('Pengaturan situs disimpan')
            ->send();
    }
}
