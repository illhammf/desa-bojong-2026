<?php

use App\Models\User;
use App\Services\AutentikasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\Support\Skenario;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->svc = app(AutentikasiService::class);
});

function pesanGalatLogin(callable $f, string $kunci = 'email'): ?string
{
    try {
        $f();
    } catch (ValidationException $e) {
        return $e->errors()[$kunci][0] ?? null;
    }

    return null;
}

it('masuk untuk keempat peran dan menentukan tujuan sesuai peran (FR-02, FR-32)', function (string $peran, string $tujuan) {
    $user = $this->svc->masuk("{$peran}@demo.test", 'password');

    expect(Auth::guard('web')->check())->toBeTrue();
    expect(Auth::guard('web')->id())->toBe($user->id);
    expect($this->svc->tujuan($user))->toBe($tujuan);
})->with([
    'masyarakat' => ['warga', AutentikasiService::TUJUAN_MASYARAKAT],
    'petugas' => ['petugas', AutentikasiService::TUJUAN_PANEL],
    'admin' => ['admin', AutentikasiService::TUJUAN_PANEL],
    'super admin' => ['superadmin', AutentikasiService::TUJUAN_PANEL],
]);

it('memberi pesan yang sama untuk email tak dikenal, kata sandi salah, dan akun nonaktif', function () {
    Skenario::user('petugas')->update(['status_akun' => 'nonaktif']);

    $pesan = [
        pesanGalatLogin(fn () => $this->svc->masuk('tidakada@uji.test', 'password')),
        pesanGalatLogin(fn () => $this->svc->masuk('warga@demo.test', 'salah-total')),
        pesanGalatLogin(fn () => $this->svc->masuk('petugas@demo.test', 'password')), // kata sandi benar, akun nonaktif
    ];

    expect($pesan)->each->toBe(AutentikasiService::PESAN_GAGAL);
    expect(Auth::guard('web')->check())->toBeFalse();
});

it('menormalkan email: huruf besar dan spasi tidak menghalangi masuk', function () {
    $user = $this->svc->masuk('  WARGA@Demo.Test ', 'password');

    expect($user->email)->toBe('warga@demo.test');
});

it('menolak email atau kata sandi kosong dengan pesan per kolom', function () {
    try {
        $this->svc->masuk('', '');
        $this->fail('seharusnya ditolak');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toEqual(['email', 'password']);
    }
});

it('memblokir percobaan beruntun walau kata sandi akhirnya benar, tanpa mengganggu akun lain', function () {
    for ($i = 0; $i < AutentikasiService::MAKS_PERCOBAAN; $i++) {
        expect(pesanGalatLogin(fn () => $this->svc->masuk('warga@demo.test', 'salah')))->toBe(AutentikasiService::PESAN_GAGAL);
    }

    expect(pesanGalatLogin(fn () => $this->svc->masuk('warga@demo.test', 'password')))->toContain('Terlalu banyak percobaan');
    expect(Auth::guard('web')->check())->toBeFalse();

    expect($this->svc->masuk('petugas@demo.test', 'password')->email)->toBe('petugas@demo.test');
});

it('menghapus hitungan percobaan setelah berhasil masuk', function () {
    for ($i = 0; $i < 3; $i++) {
        pesanGalatLogin(fn () => $this->svc->masuk('warga@demo.test', 'salah'));
    }

    $this->svc->masuk('warga@demo.test', 'password');
    $this->svc->keluar();

    for ($i = 0; $i < 4; $i++) {
        expect(pesanGalatLogin(fn () => $this->svc->masuk('warga@demo.test', 'salah')))->toBe(AutentikasiService::PESAN_GAGAL);
    }

    expect($this->svc->masuk('warga@demo.test', 'password')->email)->toBe('warga@demo.test');
});

it('menolak akun aktif yang tidak memiliki peran', function () {
    User::create(['name' => 'Tanpa Peran', 'email' => 'tanpa@uji.test', 'no_hp' => '081200000088', 'status_akun' => 'aktif', 'password' => 'password']);

    expect(pesanGalatLogin(fn () => $this->svc->masuk('tanpa@uji.test', 'password')))->toBe(AutentikasiService::PESAN_GAGAL);
    expect(Auth::guard('web')->check())->toBeFalse();
});

it('keluar menutup sesi dan tercatat pada log (FR-03, FR-51)', function () {
    $user = $this->svc->masuk('warga@demo.test', 'password');
    $this->svc->keluar();

    expect(Auth::guard('web')->check())->toBeFalse();
    expect(Skenario::logTerakhir('Autentikasi', 'logout')?->causer_id)->toBe($user->id);
});

it('mencatat masuk dan percobaan gagal pada log tanpa menyimpan kata sandi', function () {
    Skenario::user('petugas')->update(['status_akun' => 'nonaktif']);
    pesanGalatLogin(fn () => $this->svc->masuk('petugas@demo.test', 'password'));

    $gagal = Skenario::logTerakhir('Autentikasi', 'login_gagal');
    expect($gagal)->not->toBeNull();
    expect($gagal->causer_id)->toBeNull();
    expect($gagal->properties['email'])->toBe('petugas@demo.test');
    expect($gagal->properties->toJson())->not->toContain('password');

    $user = $this->svc->masuk('warga@demo.test', 'password');
    expect(Skenario::logTerakhir('Autentikasi', 'login')?->causer_id)->toBe($user->id);
});
