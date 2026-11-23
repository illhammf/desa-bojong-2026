<?php

use App\Console\Commands\PeriksaProduksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

it('mengenali kata sandi basis data bawaan atau lemah', function (string $sandi, bool $lemah) {
    expect(PeriksaProduksi::sandiLemah($sandi))->toBe($lemah);
})->with([
    'kosong' => ['', true],
    'bawaan boilerplate' => ['p455w0rd', true],
    'bawaan umum' => ['password', true],
    'huruf besar bawaan' => ['PASSWORD', true],
    'pendek' => ['Ab1!xyz', true],
    'sebelas karakter' => ['Abcdefghij1', true],
    'kuat' => ['kata-sandi-kuat-2026', false],
    'dua belas karakter acak' => ['x7Kq-92mZpL4', false],
]);

it('menolak pengaturan lokal: debug menyala, akun demo, dan kata sandi bawaan', function () {
    config(['app.debug' => true]);

    $this->artisan('app:periksa-produksi')
        ->expectsOutputToContain('APP_DEBUG harus false')
        ->expectsOutputToContain('Akun demo masih ada')
        ->expectsOutputToContain('masih memakai kata sandi "password"')
        ->assertExitCode(1);
});

it('lolos bila seluruh pengaturan siap produksi', function () {
    User::query()->where('email', 'like', '%@demo.test')->get()->each->delete();

    $sa = User::create([
        'name' => 'Super Admin Desa',
        'email' => 'superadmin@desabojong.go.id',
        'no_hp' => '081200000001',
        'status_akun' => 'aktif',
        'password' => 'kata-sandi-kuat-123',
    ]);
    $sa->assignRole('super_admin');

    config([
        'app.env' => 'production',
        'app.debug' => false,
        'app.key' => 'base64:' . base64_encode(random_bytes(32)),
        'app.url' => 'https://desa-bojong.example',
        'app.timezone' => 'Asia/Jakarta',
        'app.locale' => 'id',
        'session.secure' => true,
        'session.http_only' => true,
        'logging.channels.single.level' => 'warning',
    ]);

    $this->artisan('app:periksa-produksi')
        ->expectsOutputToContain('SIAP')
        ->assertExitCode(0);
});

it('menolak bila cookie sesi tidak aman atau tidak ada Super Admin aktif', function () {
    User::query()->where('email', 'like', '%@demo.test')->get()->each->delete();

    config([
        'app.env' => 'production',
        'app.debug' => false,
        'app.key' => 'base64:' . base64_encode(random_bytes(32)),
        'app.url' => 'https://desa-bojong.example',
        'session.secure' => false,
    ]);

    $this->artisan('app:periksa-produksi')
        ->expectsOutputToContain('SESSION_SECURE_COOKIE harus true')
        ->expectsOutputToContain('Tidak ada Super Admin aktif')
        ->assertExitCode(1);
});

it('menolak APP_URL tanpa HTTPS', function () {
    config(['app.url' => 'http://desa-bojong.example'\]\)\;

    $this->artisan('app:periksa-produksi')
        ->expectsOutputToContain('APP_URL harus diawali https://')
        ->assertExitCode(1);
});
