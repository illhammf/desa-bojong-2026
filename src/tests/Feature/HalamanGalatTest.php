<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Skenario;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Storage::fake('local');
});

it('setiap halaman galat tersedia dalam Bahasa Indonesia dan mengarahkan kembali ke beranda', function (int $kode, string $judul) {
    $html = view("errors.{$kode}")->render();

    expect($html)->toContain((string) $kode)->toContain($judul)->toContain('Kembali ke Beranda')->toContain('lang="id"');
})->with([
    [403, 'Akses ditolak'],
    [404, 'Halaman tidak ditemukan'],
    [419, 'Sesi telah berakhir'],
    [429, 'Terlalu banyak permintaan'],
    [500, 'Terjadi gangguan'],
    [503, 'Sedang dalam pemeliharaan'],
]);

it('halaman tidak ditemukan berbahasa Indonesia tanpa rincian teknis', function () {
    $this->get('/halaman-yang-tidak-ada-123')
        ->assertNotFound()
        ->assertSee('Halaman tidak ditemukan')
        ->assertDontSee('NotFoundHttpException')
        ->assertDontSee('vendor/laravel');
});

it('akses ditolak menampilkan halaman berbahasa Indonesia', function () {
    $dokumen = Skenario::ajukanSiu()->dokumen()->first();

    $this->actingAs(Skenario::user('admin'))
        ->get(route('dokumen.tampil', $dokumen))
        ->assertForbidden()
        ->assertSee('Akses ditolak');
});

it('gangguan sistem menampilkan pesan umum tanpa rincian teknis saat debug mati (NFR-19)', function () {
    config(['app.debug' => false]);
    Route::get('/uji-gangguan-sistem', fn () => throw new RuntimeException('rahasia-internal-xyz'));

    $this->get('/uji-gangguan-sistem')
        ->assertStatus(500)
        ->assertSee('Terjadi gangguan')
        ->assertDontSee('rahasia-internal-xyz')
        ->assertDontSee('RuntimeException');
});
