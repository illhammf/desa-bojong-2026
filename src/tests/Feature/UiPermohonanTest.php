<?php

use App\Enums\StatusPermohonan as S;
use App\Enums\StatusVerifikasiDokumen as V;
use App\Filament\Admin\Resources\PermohonanResource\Pages\ListPermohonan;
use App\Filament\Admin\Resources\PermohonanResource\Pages\ViewPermohonan;
use App\Filament\Admin\Resources\PermohonanResource\RelationManagers\DokumenRelationManager;
use App\Filament\Admin\Resources\PermohonanResource\RelationManagers\RiwayatRelationManager;
use App\Services\AlurStatusService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Skenario;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Storage::fake('local');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->petugas = Skenario::user('petugas');
    $this->p = Skenario::ajukanSiu();
});

it('petugas melihat seluruh permohonan pada daftar (FR-17)', function () {
    $lain = Skenario::ajukanSiu(Skenario::wargaBaru());

    $this->actingAs($this->petugas);

    Livewire::test(ListPermohonan::class)->assertCanSeeTableRecords([$this->p, $lain]);
});

it('daftar permohonan tertutup untuk selain petugas (BR-05, BR-33)', function (string $peran) {
    $this->actingAs(Skenario::user($peran));

    Livewire::test(ListPermohonan::class)->assertForbidden();
})->with(['admin', 'superadmin', 'warga']);

it('tombol aksi pada detail permohonan mengikuti status', function (S $status, array $tampil) {
    Skenario::paksaStatus($this->p, $status);
    $this->actingAs($this->petugas);

    $uji = Livewire::test(ViewPermohonan::class, ['record' => $this->p->getRouteKey()]);

    foreach (['mulaiPemeriksaan', 'mintaPerbaikan', 'proses', 'siapDiambil'] as $aksi) {
        if (in_array($aksi, $tampil, true)) {
            $uji->assertActionVisible($aksi);
        } else {
            $uji->assertActionHidden($aksi);
        }
    }
})->with([
    'diajukan' => [S::Diajukan, ['mulaiPemeriksaan']],
    'dalam pemeriksaan' => [S::DalamPemeriksaan, ['mintaPerbaikan', 'proses']],
    'perlu perbaikan' => [S::PerluPerbaikan, []],
    'diproses' => [S::Diproses, ['siapDiambil']],
    'siap diambil' => [S::SiapDiambil, []],
]);

it('petugas menjalankan alur lengkap lewat antarmuka sampai Siap Diambil (FR-19, FR-23, FR-24)', function () {
    $this->actingAs($this->petugas);
    $buka = fn () => Livewire::test(ViewPermohonan::class, ['record' => $this->p->getRouteKey()]);

    $buka()->callAction('mulaiPemeriksaan');
    expect($this->p->refresh()->status)->toBe(S::DalamPemeriksaan);

    Skenario::tandaiSesuai($this->p);
    $buka()->callAction('proses');
    expect($this->p->refresh()->status)->toBe(S::Diproses);

    $buka()->callAction('siapDiambil');
    expect($this->p->refresh()->status)->toBe(S::SiapDiambil);

    expect($this->p->riwayat()->count())->toBe(4);
});

it('Proses ditolak lewat antarmuka bila masih ada dokumen yang belum Sesuai', function () {
    Skenario::paksaStatus($this->p, S::DalamPemeriksaan);
    $this->actingAs($this->petugas);

    Livewire::test(ViewPermohonan::class, ['record' => $this->p->getRouteKey()])->callAction('proses');

    expect($this->p->refresh()->status)->toBe(S::DalamPemeriksaan);
    expect($this->p->riwayat()->count())->toBe(1);
});

it('permintaan perbaikan mewajibkan catatan yang memadai lalu tersimpan untuk pemohon (FR-21)', function () {
    Skenario::paksaStatus($this->p, S::DalamPemeriksaan);
    $this->actingAs($this->petugas);
    $buka = fn () => Livewire::test(ViewPermohonan::class, ['record' => $this->p->getRouteKey()]);

    $buka()->callAction('mintaPerbaikan', data: ['catatan' => ''])->assertHasActionErrors(['catatan']);
    $buka()->callAction('mintaPerbaikan', data: ['catatan' => 'pendek'])->assertHasActionErrors(['catatan']);
    expect($this->p->refresh()->status)->toBe(S::DalamPemeriksaan);

    $buka()
        ->callAction('mintaPerbaikan', data: ['catatan' => 'Foto KTP buram, mohon unggah ulang.'])
        ->assertHasNoActionErrors();

    expect($this->p->refresh()->status)->toBe(S::PerluPerbaikan);
    expect($this->p->catatan_petugas)->toBe('Foto KTP buram, mohon unggah ulang.');
    expect($this->p->riwayat()->get()->last()->keterangan)->toBe('Foto KTP buram, mohon unggah ulang.');
});

it('petugas menandai dokumen Sesuai atau Tidak Sesuai hanya saat Dalam Pemeriksaan (FR-20)', function () {
    $dokumen = $this->p->dokumen()->first();
    $this->actingAs($this->petugas);

    Livewire::test(DokumenRelationManager::class, ['ownerRecord' => $this->p, 'pageClass' => ViewPermohonan::class])
        ->assertTableActionHidden('sesuai', $dokumen)
        ->assertTableActionHidden('tidakSesuai', $dokumen);

    Skenario::paksaStatus($this->p, S::DalamPemeriksaan);

    $uji = Livewire::test(DokumenRelationManager::class, ['ownerRecord' => $this->p, 'pageClass' => ViewPermohonan::class])
        ->assertTableActionVisible('sesuai', $dokumen)
        ->callTableAction('sesuai', $dokumen);

    // Hasil tindakan diperiksa lewat basis data; model dimuat ulang agar visibilitas tombol dinilai dari data terbaru.
    expect($dokumen->refresh()->status_verifikasi)->toBe(V::Sesuai);

    $uji->assertTableActionHidden('sesuai', $dokumen)
        ->assertTableActionVisible('tidakSesuai', $dokumen);

    Livewire::test(DokumenRelationManager::class, ['ownerRecord' => $this->p, 'pageClass' => ViewPermohonan::class])
        ->callTableAction('tidakSesuai', $dokumen);

    expect($dokumen->refresh()->status_verifikasi)->toBe(V::TidakSesuai);
});

it('administrator tidak dapat menandai hasil verifikasi dokumen (BR-05)', function () {
    Skenario::paksaStatus($this->p, S::DalamPemeriksaan);
    $dokumen = $this->p->dokumen()->first();

    $this->actingAs(Skenario::user('admin'));

    Livewire::test(DokumenRelationManager::class, ['ownerRecord' => $this->p, 'pageClass' => ViewPermohonan::class])
        ->assertTableActionHidden('sesuai', $dokumen)
        ->assertTableActionHidden('tidakSesuai', $dokumen);
});

it('petugas melihat riwayat perubahan status beserta pelakunya (FR-25)', function () {
    app(AlurStatusService::class)->ubah($this->p, S::DalamPemeriksaan, $this->petugas);
    $this->actingAs($this->petugas);

    Livewire::test(RiwayatRelationManager::class, ['ownerRecord' => $this->p, 'pageClass' => ViewPermohonan::class])
        ->assertCanSeeTableRecords($this->p->riwayat()->get())
        ->assertSee('Dalam Pemeriksaan')
        ->assertSee('Diajukan')
        ->assertSee($this->petugas->name)
        ->assertSee('Sistem');
});
