<?php

use App\Models\Masyarakat;
use App\Models\User;
use App\Services\RegistrasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Skenario;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->svc = app(RegistrasiService::class);
    $this->masukan = [
        'nama' => 'Siti Warga Baru',
        'nik' => '3603190101900001',
        'alamat' => 'Kp. Bojong Baru, RT 001/RW 001, Desa Bojong, Kec. Cikupa',
        'email' => 'siti.baru@uji.test',
        'no_hp' => '081234567890',
        'password' => 'rahasia-123',
        'password_confirmation' => 'rahasia-123',
        'pernyataan' => '1',
    ];
});

afterEach(fn () => Masyarakat::flushEventListeners());

it('mendaftarkan masyarakat: akun aktif, peran masyarakat, profil lengkap, kata sandi ter-hash (FR-01, NFR-03)', function () {
    $user = $this->svc->daftar($this->masukan);

    expect($user->exists)->toBeTrue();
    expect($user->status_akun)->toBe('aktif');
    expect($user->getRoleNames()->all())->toEqual(['masyarakat']);
    expect($user->masyarakat->nik)->toBe('3603190101900001');
    expect($user->masyarakat->alamat)->toContain('Bojong Baru');
    expect($user->password)->not->toBe('rahasia-123');
    expect(Hash::check('rahasia-123', $user->password))->toBeTrue();
    expect(Auth::guard('web')->check())->toBeFalse(); // tidak login otomatis
});

it('menolak pendaftaran kosong dan menyebut setiap bagian yang kurang', function () {
    $galat = Skenario::galat(fn () => $this->svc->daftar([]));

    foreach (['nama', 'nik', 'alamat', 'email', 'no_hp', 'password', 'pernyataan'] as $kunci) {
        expect($galat)->toContain($kunci);
    }
    expect(User::count())->toBe(4);
});

it('menolak NIK yang bukan 16 digit angka', function (string $nik) {
    expect(Skenario::galat(fn () => $this->svc->daftar([...$this->masukan, 'nik' => $nik])))->toContain('nik');
})->with(['15 digit' => '360319010190000', '17 digit' => '36031901019000012', 'huruf' => '36031901019000ab', 'simbol' => '3603-1901-0190-001', 'kosong' => '']);

it('menolak pendaftaran dengan input bukan teks tanpa menimbulkan galat sistem', function () {
    $galat = Skenario::galat(fn () => $this->svc->daftar([...$this->masukan, 'nik' => ['x'], 'email' => ['y']]));

    expect($galat)->toContain('nik')->toContain('email');
});

it('menerima NIK yang diketik dengan spasi dan menyimpannya tanpa spasi', function () {
    $user = $this->svc->daftar([...$this->masukan, 'nik' => '3603 1901 0190 0001']);

    expect($user->masyarakat->nik)->toBe('3603190101900001');
});

it('menolak NIK yang sudah terdaftar pada akun lain', function () {
    $nikWarga = Skenario::user('warga')->masyarakat->nik;

    expect(Skenario::galat(fn () => $this->svc->daftar([...$this->masukan, 'nik' => $nikWarga])))->toContain('nik');
    expect(User::count())->toBe(4);
});

it('menolak email yang sudah digunakan, termasuk beda huruf besar dan spasi', function (string $email) {
    expect(Skenario::galat(fn () => $this->svc->daftar([...$this->masukan, 'email' => $email])))->toContain('email');
})->with(['sama persis' => 'warga@demo.test', 'huruf besar' => 'WARGA@DEMO.TEST', 'berspasi' => '  warga@demo.test  ']);

it('menolak kata sandi pendek atau konfirmasi yang tidak sama', function (string $sandi, string $konfirmasi) {
    expect(Skenario::galat(fn () => $this->svc->daftar([...$this->masukan, 'password' => $sandi, 'password_confirmation' => $konfirmasi])))
        ->toContain('password');
})->with([
    'terlalu pendek' => ['pendek1', 'pendek1'],
    'konfirmasi beda' => ['rahasia-123', 'rahasia-124'],
]);

it('menolak nomor WhatsApp yang bukan nomor seluler Indonesia', function (string $noHp) {
    expect(Skenario::galat(fn () => $this->svc->daftar([...$this->masukan, 'no_hp' => $noHp])))->toContain('no_hp');
})->with(['terlalu pendek' => '12345', 'kode negara lain' => '+1234567890', 'ada huruf' => '0812abc4567', 'telepon rumah' => '021123456']);

it('menerima berbagai penulisan nomor seluler dan menyimpannya tanpa spasi atau tanda hubung', function () {
    $kasus = [
        '0812 3456 7890' => '081234567890',
        '+62 812-3456-7890' => '+6281234567890',
        '6281234567890' => '6281234567890',
    ];

    $i = 10;
    foreach ($kasus as $ketik => $tersimpan) {
        $user = $this->svc->daftar([...$this->masukan, 'nik' => '36031901019000' . $i, 'email' => "nomor{$i}@uji.test", 'no_hp' => $ketik]);
        expect($user->no_hp)->toBe($tersimpan);
        $i++;
    }
});

it('menolak pendaftaran tanpa persetujuan pernyataan kebenaran data', function () {
    expect(Skenario::galat(fn () => $this->svc->daftar([...$this->masukan, 'pernyataan' => null])))->toContain('pernyataan');
    expect(Skenario::galat(fn () => $this->svc->daftar([...$this->masukan, 'pernyataan' => '0'])))->toContain('pernyataan');
});

it('selalu membuat peran masyarakat dan mengabaikan kolom peran atau status dari luar', function () {
    $user = $this->svc->daftar([...$this->masukan, 'role' => 'super_admin', 'roles' => ['admin'], 'status_akun' => 'nonaktif', 'is_admin' => true]);

    expect($user->getRoleNames()->all())->toEqual(['masyarakat']);
    expect($user->status_akun)->toBe('aktif');
});

it('tidak meninggalkan akun setengah jadi bila penyimpanan profil gagal', function () {
    Masyarakat::creating(function () {
        throw new RuntimeException('simulasi gagal');
    });

    expect(fn () => $this->svc->daftar($this->masukan))->toThrow(RuntimeException::class);
    expect(User::where('email', 'siti.baru@uji.test')->exists())->toBeFalse();
    expect(User::count())->toBe(4);
});

it('mencatat pembuatan akun pada log tanpa menyimpan kata sandi', function () {
    $user = $this->svc->daftar($this->masukan);

    $a = Skenario::logTerakhir('Akun', 'created');
    expect($a)->not->toBeNull();
    expect($a->subject_id)->toBe($user->id);
    expect($a->properties->toJson())->not->toContain('rahasia')->not->toContain('password');
});
