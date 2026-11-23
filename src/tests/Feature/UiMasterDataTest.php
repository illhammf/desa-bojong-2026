<?php

use App\Filament\Admin\Resources\JenisLayananResource\Pages\CreateJenisLayanan;
use App\Filament\Admin\Resources\JenisLayananResource\Pages\EditJenisLayanan;
use App\Filament\Admin\Resources\JenisLayananResource\Pages\ListJenisLayanans;
use App\Filament\Admin\Resources\JenisLayananResource\RelationManagers\PersyaratanRelationManager;
use App\Models\JenisLayanan;
use App\Models\Persyaratan;
use Filament\Facades\Filament;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Skenario;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Storage::fake('local');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->admin = Skenario::user('admin');
});

it('administrator membuat layanan baru (FR-29)', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateJenisLayanan::class)
        ->fillForm([
            'kode' => 'UJI',
            'nama_layanan' => 'Layanan Uji Coba',
            'deskripsi' => 'Deskripsi layanan uji.',
            'estimasi_waktu' => '1 Hari Kerja',
            'aktif' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $layanan = JenisLayanan::where('kode', 'UJI')->first();

    expect($layanan)->not->toBeNull();
    expect($layanan->nama_layanan)->toBe('Layanan Uji Coba');
    expect($layanan->aktif)->toBeTrue();
});

it('formulir layanan menolak isian yang tidak sah', function (array $isian, string $kolom) {
    $this->actingAs($this->admin);

    Livewire::test(CreateJenisLayanan::class)
        ->fillForm($isian)
        ->call('create')
        ->assertHasFormErrors([$kolom]);

    expect(JenisLayanan::count())->toBe(20);
})->with([
    'kode ganda' => [['kode' => 'SIU', 'nama_layanan' => 'Duplikat'], 'kode'],
    'kode berspasi' => [['kode' => 'AB CD', 'nama_layanan' => 'Uji'], 'kode'],
    'kode terlalu panjang' => [['kode' => 'ABCDEFGHIJK', 'nama_layanan' => 'Uji'], 'kode'],
    'nama kosong' => [['kode' => 'UJI2', 'nama_layanan' => ''], 'nama_layanan'],
]);

it('kode layanan terkunci setelah layanan memiliki permohonan', function () {
    $this->actingAs($this->admin);
    $siu = JenisLayanan::where('kode', 'SIU')->first();

    Livewire::test(EditJenisLayanan::class, ['record' => $siu->getRouteKey()])->assertFormFieldIsEnabled('kode');

    Skenario::ajukanSiu();

    Livewire::test(EditJenisLayanan::class, ['record' => $siu->getRouteKey()])->assertFormFieldIsDisabled('kode');
});

it('layanan diaktifkan dan dinonaktifkan lewat tombol (PRD 7.11)', function () {
    $this->actingAs($this->admin);
    $umm = JenisLayanan::where('kode', 'UMM')->first();

    Livewire::test(ListJenisLayanans::class)->callTableAction('toggleAktif', $umm);
    expect($umm->refresh()->aktif)->toBeFalse();

    Livewire::test(ListJenisLayanans::class)->callTableAction('toggleAktif', $umm);
    expect($umm->refresh()->aktif)->toBeTrue();
});

it('layanan yang sudah memiliki permohonan tidak dapat dihapus, yang belum dapat', function () {
    $this->actingAs($this->admin);
    Skenario::ajukanSiu();
    $siu = JenisLayanan::where('kode', 'SIU')->first();
    $umm = JenisLayanan::where('kode', 'UMM')->first();

    Livewire::test(ListJenisLayanans::class)->callTableAction(DeleteAction::class, $siu);
    expect(JenisLayanan::where('kode', 'SIU')->exists())->toBeTrue();

    Livewire::test(ListJenisLayanans::class)->callTableAction(DeleteAction::class, $umm);
    expect(JenisLayanan::where('kode', 'UMM')->exists())->toBeFalse();
});

it('master data layanan tertutup bagi petugas dan masyarakat, terbuka bagi Super Admin', function () {
    foreach (['petugas', 'warga'] as $peran) {
        $this->actingAs(Skenario::user($peran));
        Livewire::test(ListJenisLayanans::class)->assertForbidden();
    }

    $this->actingAs(Skenario::user('superadmin'));
    Livewire::test(ListJenisLayanans::class)->assertStatus(200);
});

it('administrator menambah persyaratan isian bertipe pilihan dengan urutan otomatis (FR-30)', function () {
    $this->actingAs($this->admin);
    $umm = JenisLayanan::where('kode', 'UMM')->first();

    Livewire::test(PersyaratanRelationManager::class, ['ownerRecord' => $umm, 'pageClass' => EditJenisLayanan::class])
        ->callTableAction(CreateAction::class, data: [
            'nama_persyaratan' => 'Jenis Acara',
            'tipe_persyaratan' => 'isian',
            'tipe_input' => 'pilihan',
            'opsi' => ['Pernikahan', 'Khitanan'],
            'wajib' => true,
            'aktif' => true,
            'keterangan' => 'Pilih salah satu.',
        ])
        ->assertHasNoTableActionErrors();

    $baru = Persyaratan::where('nama_persyaratan', 'Jenis Acara')->first();

    expect($baru)->not->toBeNull();
    expect($baru->opsi)->toEqual(['Pernikahan', 'Khitanan']);
    expect($baru->wajib)->toBeTrue();
    expect($baru->urutan)->toBe(2);
});

it('persyaratan bertipe pilihan wajib memiliki daftar pilihan, dan nama wajib diisi', function () {
    $this->actingAs($this->admin);
    $umm = JenisLayanan::where('kode', 'UMM')->first();

    Livewire::test(PersyaratanRelationManager::class, ['ownerRecord' => $umm, 'pageClass' => EditJenisLayanan::class])
        ->callTableAction(CreateAction::class, data: [
            'nama_persyaratan' => 'Tanpa Pilihan',
            'tipe_persyaratan' => 'isian',
            'tipe_input' => 'pilihan',
            'opsi' => [],
            'wajib' => true,
            'aktif' => true,
        ])
        ->assertHasTableActionErrors(['opsi']);

    Livewire::test(PersyaratanRelationManager::class, ['ownerRecord' => $umm, 'pageClass' => EditJenisLayanan::class])
        ->callTableAction(CreateAction::class, data: [
            'nama_persyaratan' => '',
            'tipe_persyaratan' => 'dokumen',
            'wajib' => true,
            'aktif' => true,
        ])
        ->assertHasTableActionErrors(['nama_persyaratan']);

    expect(Persyaratan::where('jenis_layanan_id', $umm->id)->count())->toBe(1);
});

it('persyaratan yang sudah dipakai permohonan tidak dapat dihapus, yang belum dipakai dapat', function () {
    $this->actingAs($this->admin);
    Skenario::ajukanSiu();
    $siu = JenisLayanan::where('kode', 'SIU')->first();
    $dipakai = Persyaratan::where('jenis_layanan_id', $siu->id)->where('nama_persyaratan', 'Jenis Usaha')->first();
    $bebas = Persyaratan::create([
        'jenis_layanan_id' => $siu->id,
        'nama_persyaratan' => 'Persyaratan Uji',
        'tipe_persyaratan' => 'isian',
        'tipe_input' => 'teks',
        'wajib' => false,
    ]);

    Livewire::test(PersyaratanRelationManager::class, ['ownerRecord' => $siu, 'pageClass' => EditJenisLayanan::class])
        ->callTableAction(DeleteAction::class, $dipakai);
    expect(Persyaratan::find($dipakai->id))->not->toBeNull();

    Livewire::test(PersyaratanRelationManager::class, ['ownerRecord' => $siu, 'pageClass' => EditJenisLayanan::class])
        ->callTableAction(DeleteAction::class, $bebas);
    expect(Persyaratan::find($bebas->id))->toBeNull();
});
