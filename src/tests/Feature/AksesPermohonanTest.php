<?php

use App\Models\Permohonan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Skenario;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Storage::fake('local');
    $this->warga = Skenario::user('warga');
    $this->lain = Skenario::wargaBaru();
    $this->milikku = Skenario::ajukanSiu($this->warga);
    $this->milikLain = Skenario::ajukanSiu($this->lain);
});

it('scope milik hanya mengembalikan permohonan pemiliknya (FR-11, BR-02)', function () {
    expect(Permohonan::milik($this->warga)->pluck('id')->all())->toEqual([$this->milikku->id]);
    expect(Permohonan::milik($this->lain)->pluck('id')->all())->toEqual([$this->milikLain->id]);
    expect(Permohonan::milik(Skenario::user('petugas'))->count())->toBe(0);
});

it('masyarakat hanya dapat melihat permohonan miliknya, petugas semuanya, pengelola tidak satu pun (FR-43)', function () {
    $petugas = Skenario::user('petugas');

    expect($this->warga->can('view', $this->milikku))->toBeTrue();
    expect($this->warga->can('view', $this->milikLain))->toBeFalse();
    expect($this->lain->can('view', $this->milikLain))->toBeTrue();
    expect($this->lain->can('view', $this->milikku))->toBeFalse();

    expect($petugas->can('view', $this->milikku))->toBeTrue();
    expect($petugas->can('view', $this->milikLain))->toBeTrue();

    foreach (['admin', 'superadmin'] as $peran) {
        $akun = Skenario::user($peran);
        expect($akun->can('view', $this->milikku))->toBeFalse();
        expect($akun->can('view', $this->milikLain))->toBeFalse();
    }
});

it('daftar permohonan hanya untuk masyarakat dan petugas', function () {
    expect($this->warga->can('viewAny', Permohonan::class))->toBeTrue();
    expect(Skenario::user('petugas')->can('viewAny', Permohonan::class))->toBeTrue();
    expect(Skenario::user('admin')->can('viewAny', Permohonan::class))->toBeFalse();
    expect(Skenario::user('superadmin')->can('viewAny', Permohonan::class))->toBeFalse();
});

it('hanya masyarakat aktif yang dapat membuka formulir pengajuan (BR-01)', function () {
    expect($this->warga->can('create', Permohonan::class))->toBeTrue();

    foreach (['petugas', 'admin', 'superadmin'] as $peran) {
        expect(Skenario::user($peran)->can('create', Permohonan::class))->toBeFalse();
    }

    $this->warga->update(['status_akun' => 'nonaktif']);
    expect($this->warga->refresh()->can('create', Permohonan::class))->toBeFalse();
});

it('tidak seorang pun dapat mengubah atau menghapus permohonan langsung, hanya lewat service', function () {
    foreach (['warga', 'petugas', 'admin', 'superadmin'] as $peran) {
        $akun = Skenario::user($peran);
        expect($akun->can('update', $this->milikku))->toBeFalse();
        expect($akun->can('delete', $this->milikku))->toBeFalse();
        expect($akun->can('deleteAny', Permohonan::class))->toBeFalse();
    }
});

it('akun nonaktif kehilangan akses permohonan', function () {
    $petugas = Skenario::user('petugas');
    $petugas->update(['status_akun' => 'nonaktif']);
    $this->warga->update(['status_akun' => 'nonaktif']);

    expect($petugas->refresh()->can('view', $this->milikku))->toBeFalse();
    expect($this->warga->refresh()->can('view', $this->milikku))->toBeFalse();
});

it('pengguna yang dinonaktifkan di tengah sesi langsung dikeluarkan saat membuka dokumen (FR-02)', function () {
    $dokumen = $this->milikku->dokumen()->first();

    $this->actingAs($this->warga);
    $this->get(route('dokumen.tampil', $dokumen))->assertOk();

    $this->warga->update(['status_akun' => 'nonaktif']);

    $this->get(route('dokumen.tampil', $dokumen))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('dokumen mengikuti kebijakan akses permohonan: pemilik dan petugas boleh, selainnya tidak', function () {
    $dokumen = $this->milikku->dokumen()->first();

    $this->actingAs($this->warga)->get(route('dokumen.tampil', $dokumen))->assertOk();
    $this->actingAs($this->lain)->get(route('dokumen.tampil', $dokumen))->assertForbidden();
    $this->actingAs(Skenario::user('petugas'))->get(route('dokumen.tampil', $dokumen))->assertOk();
    $this->actingAs(Skenario::user('admin'))->get(route('dokumen.tampil', $dokumen))->assertForbidden();
});
