<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Skenario;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Storage::fake('local');
    $this->warga = Skenario::user('warga');
    $this->permohonan = Skenario::ajukanSiu($this->warga);
    $this->dokumen = $this->permohonan->dokumen()->first();
});

it('pemilik permohonan dan petugas boleh membuka dokumen', function () {
    $this->actingAs($this->warga)
        ->get(route('dokumen.tampil', $this->dokumen))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs(Skenario::user('petugas'))
        ->get(route('dokumen.tampil', $this->dokumen))
        ->assertOk();
});

it('warga lain, administrator, dan super admin ditolak membuka dokumen', function () {
    $this->actingAs(Skenario::wargaBaru())->get(route('dokumen.tampil', $this->dokumen))->assertForbidden();
    $this->actingAs(Skenario::user('admin'))->get(route('dokumen.tampil', $this->dokumen))->assertForbidden();
    $this->actingAs(Skenario::user('superadmin'))->get(route('dokumen.tampil', $this->dokumen))->assertForbidden();
});

it('pengunjung yang belum login diarahkan ke halaman login', function () {
    $this->get(route('dokumen.tampil', $this->dokumen))->assertRedirect(route('login'));
});

it('berkas yang hilang dari penyimpanan menghasilkan 404', function () {
    Storage::disk('local')->delete($this->dokumen->lokasi_file);

    $this->actingAs($this->warga)->get(route('dokumen.tampil', $this->dokumen))->assertNotFound();
});

it('berkas tidak dapat dibuka lewat URL publik', function () {
    $this->get('/storage/' . $this->dokumen->lokasi_file)->assertNotFound();
});
