<?php

use App\Enums\StatusPermohonan as S;
use App\Filament\Admin\Pages\LaporanPage;
use App\Filament\Admin\Pages\PengaturanSitusPage;
use App\Filament\Admin\Widgets\PermohonanTerbaruWidget;
use App\Filament\Admin\Widgets\StatistikPengelolaWidget;
use App\Filament\Admin\Widgets\StatistikPetugasWidget;
use App\Models\PengaturanSitus;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Skenario;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Storage::fake('local');
    $this->withoutVite();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// ------------------------------------------------------------ Pengaturan Situs

it('administrator menyimpan pengaturan situs (konten dinamis)', function () {
    $this->actingAs(Skenario::user('admin'));

    Livewire::test(PengaturanSitusPage::class)
        ->assertFormSet(['nama_situs' => 'Sistem Pelayanan Administrasi Desa Bojong'])
        ->fillForm([
            'nama_situs' => 'Nama Situs Ubahan',
            'telepon' => '(021) 5961234',
            'whatsapp' => '081234567890',
            'email' => 'layanan@desabojong.example',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    PengaturanSitus::lupakan();
    $pengaturan = PengaturanSitus::ambil();

    expect($pengaturan->nama_situs)->toBe('Nama Situs Ubahan');
    expect($pengaturan->telepon)->toBe('(021) 5961234');
    expect($pengaturan->email)->toBe('layanan@desabojong.example');
    expect(PengaturanSitus::count())->toBe(1);
});

it('pengaturan situs menolak isian yang tidak sah', function (array $isian, string $kolom) {
    $this->actingAs(Skenario::user('admin'));

    Livewire::test(PengaturanSitusPage::class)
        ->fillForm($isian)
        ->call('save')
        ->assertHasFormErrors([$kolom]);
})->with([
    'nama situs kosong' => [['nama_situs' => ''], 'nama_situs'],
    'email tidak valid' => [['email' => 'bukan-email'], 'email'],
    'langkah alur dikosongkan' => [['langkah_alur' => []], 'langkah_alur'],
]);

// ------------------------------------------------------------ Laporan

it('laporan menyaring tabel dan ringkasan berdasarkan status (FR-26, FR-31, FR-44)', function () {
    $a = Skenario::ajukanSiu();
    $b = Skenario::ajukanSiu();
    Skenario::paksaStatus($b, S::Diproses);

    $this->actingAs(Skenario::user('petugas'));

    $uji = Livewire::test(LaporanPage::class)->assertCanSeeTableRecords([$a, $b]);
    expect($uji->instance()->ringkasan()['total'])->toBe(2);

    $uji->filterTable('status', S::Diproses->value)
        ->assertCanSeeTableRecords([$b])
        ->assertCanNotSeeTableRecords([$a]);

    $ringkasan = $uji->instance()->ringkasan();
    expect($ringkasan['total'])->toBe(1);
    expect($ringkasan['statuses']['diproses']['jumlah'])->toBe(1);
    expect($ringkasan['statuses']['diajukan']['jumlah'])->toBe(0);
});

it('tombol Periksa pada laporan hanya tampil bagi petugas, bukan pengelola (BR-05, BR-33)', function () {
    $p = Skenario::ajukanSiu();

    $this->actingAs(Skenario::user('petugas'));
    Livewire::test(LaporanPage::class)->assertTableActionVisible('periksa', $p);

    foreach (['admin', 'superadmin'] as $peran) {
        $this->actingAs(Skenario::user($peran));
        Livewire::test(LaporanPage::class)->assertTableActionHidden('periksa', $p);
    }
});

// ------------------------------------------------------------ Dashboard

it('widget dashboard tampil sesuai peran', function () {
    $this->actingAs(Skenario::user('petugas'));
    expect(StatistikPetugasWidget::canView())->toBeTrue();
    expect(StatistikPengelolaWidget::canView())->toBeFalse();

    foreach (['admin', 'superadmin'] as $peran) {
        $this->actingAs(Skenario::user($peran));
        expect(StatistikPetugasWidget::canView())->toBeFalse();
        expect(StatistikPengelolaWidget::canView())->toBeTrue();
    }

    $this->actingAs(Skenario::user('warga'));
    expect(StatistikPetugasWidget::canView())->toBeFalse();
    expect(StatistikPengelolaWidget::canView())->toBeFalse();
});

it('widget statistik menampilkan ringkasan', function () {
    Skenario::ajukanSiu();

    $this->actingAs(Skenario::user('petugas'));
    Livewire::test(StatistikPetugasWidget::class)->assertSee('Total Permohonan')->assertSee('Dalam Pemeriksaan');

    $this->actingAs(Skenario::user('admin'));
    Livewire::test(StatistikPengelolaWidget::class)->assertSee('Total Pengguna')->assertSee('Layanan Aktif');
});

it('permohonan terbaru menampilkan tombol Periksa hanya bagi petugas', function () {
    $p = Skenario::ajukanSiu();

    $this->actingAs(Skenario::user('petugas'));
    Livewire::test(PermohonanTerbaruWidget::class)
        ->assertCanSeeTableRecords([$p])
        ->assertTableActionVisible('periksa', $p);

    $this->actingAs(Skenario::user('admin'));
    Livewire::test(PermohonanTerbaruWidget::class)
        ->assertCanSeeTableRecords([$p])
        ->assertTableActionHidden('periksa', $p);
});

// ------------------------------------------------------------ Log Aktivitas

it('halaman Log Aktivitas hanya dapat dibuka Super Admin (FR-49)', function (string $peran, int $status) {
    $resource = config('filament-logger.activity_resource');
    $alamat = $resource::getUrl('index', panel: 'admin');

    $this->actingAs(Skenario::user($peran))->get($alamat)->assertStatus($status);
})->with([
    'super admin' => ['superadmin', 200],
    'administrator' => ['admin', 403],
    'petugas' => ['petugas', 403],
    'masyarakat' => ['warga', 403],
]);
