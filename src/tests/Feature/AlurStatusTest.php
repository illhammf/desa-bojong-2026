<?php

use App\Enums\StatusPermohonan as S;
use App\Enums\StatusVerifikasiDokumen as V;
use App\Models\Permohonan;
use App\Models\Persyaratan;
use App\Models\User;
use App\Services\AlurStatusService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Skenario;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Storage::fake('local');
    $this->alur = app(AlurStatusService::class);
    $this->petugas = Skenario::user('petugas');
    $this->p = Skenario::ajukanSiu();
});

it('tabel transisi sesuai PRD 7.7', function () {
    expect(S::Diajukan->transisi())->toEqual([S::DalamPemeriksaan]);
    expect(S::DalamPemeriksaan->transisi())->toEqual([S::PerluPerbaikan, S::Diproses]);
    expect(S::PerluPerbaikan->transisi())->toEqual([S::DalamPemeriksaan]);
    expect(S::Diproses->transisi())->toEqual([S::SiapDiambil]);
    expect(S::SiapDiambil->transisi())->toEqual([]);
    expect(S::cases())->toHaveCount(5);
});

it('petugas menjalankan alur sah sampai Siap Diambil dengan riwayat berurutan', function () {
    $this->alur->ubah($this->p, S::DalamPemeriksaan, $this->petugas);
    Skenario::tandaiSesuai($this->p);
    $this->alur->ubah($this->p->refresh(), S::Diproses, $this->petugas);
    $akhir = $this->alur->ubah($this->p->refresh(), S::SiapDiambil, $this->petugas);

    expect($akhir->status)->toBe(S::SiapDiambil);

    $riwayat = $akhir->riwayat()->get();
    expect($riwayat->pluck('status')->all())->toEqual([S::Diajukan, S::DalamPemeriksaan, S::Diproses, S::SiapDiambil]);
    expect($riwayat[0]->petugas_id)->toBeNull();
    expect($riwayat[1]->petugas_id)->toBe($this->petugas->id);
});

it('perbaikan: petugas meminta dengan catatan lalu sistem mengembalikan ke Dalam Pemeriksaan', function () {
    $this->alur->ubah($this->p, S::DalamPemeriksaan, $this->petugas);
    $p = $this->alur->ubah($this->p->refresh(), S::PerluPerbaikan, $this->petugas, 'KK buram.');

    expect($p->status)->toBe(S::PerluPerbaikan);
    expect($p->catatan_petugas)->toBe('KK buram.');

    $p = $this->alur->ubah($p->refresh(), S::DalamPemeriksaan, null, 'Pemohon mengirim perbaikan.');
    expect($p->status)->toBe(S::DalamPemeriksaan);
});

it('menolak admin, super admin, dan masyarakat mengubah status (BR-05, BR-33)', function (string $peran) {
    $this->alur->ubah($this->p, S::DalamPemeriksaan, $this->petugas);
    $sebelum = $this->p->refresh()->riwayat()->count();

    expect(fn () => $this->alur->ubah($this->p->refresh(), S::PerluPerbaikan, Skenario::user($peran), 'catatan'))
        ->toThrow(AuthorizationException::class);

    expect($this->p->refresh()->status)->toBe(S::DalamPemeriksaan);
    expect($this->p->riwayat()->count())->toBe($sebelum);
})->with(['admin', 'superadmin', 'warga']);

it('menolak akun petugas yang belum memiliki profil petugas', function () {
    $tanpaProfil = Skenario::buatUser('petugas', 'petugasbaru@uji.test');

    expect(fn () => $this->alur->ubah($this->p, S::DalamPemeriksaan, $tanpaProfil))
        ->toThrow(DomainException::class, 'belum memiliki data profil petugas');
});

it('menolak perubahan status di luar alur', function (S $asal, S $tujuan) {
    $this->p->update(['status' => $asal]);

    expect(fn () => $this->alur->ubah($this->p->refresh(), $tujuan, $this->petugas, 'catatan'))
        ->toThrow(DomainException::class);
    expect($this->p->refresh()->status)->toBe($asal);
})->with([
    'Diajukan ke Diproses' => [S::Diajukan, S::Diproses],
    'Diajukan ke Siap Diambil' => [S::Diajukan, S::SiapDiambil],
    'Diajukan ke Perlu Perbaikan' => [S::Diajukan, S::PerluPerbaikan],
    'Dalam Pemeriksaan ke Siap Diambil' => [S::DalamPemeriksaan, S::SiapDiambil],
    'Dalam Pemeriksaan ke Diajukan' => [S::DalamPemeriksaan, S::Diajukan],
    'Diproses ke Dalam Pemeriksaan' => [S::Diproses, S::DalamPemeriksaan],
    'Siap Diambil ke Diproses' => [S::SiapDiambil, S::Diproses],
    'Siap Diambil ke Diajukan' => [S::SiapDiambil, S::Diajukan],
]);

it('memblokir Diproses selama ada dokumen yang belum Sesuai', function () {
    $this->alur->ubah($this->p, S::DalamPemeriksaan, $this->petugas);

    expect(fn () => $this->alur->ubah($this->p->refresh(), S::Diproses, $this->petugas))
        ->toThrow(DomainException::class, 'ditandai Sesuai');

    $this->p->dokumen()->first()->update(['status_verifikasi' => V::Sesuai->value]);
    expect(fn () => $this->alur->ubah($this->p->refresh(), S::Diproses, $this->petugas))
        ->toThrow(DomainException::class, 'ditandai Sesuai');

    Skenario::tandaiSesuai($this->p);
    expect($this->alur->ubah($this->p->refresh(), S::Diproses, $this->petugas)->status)->toBe(S::Diproses);
});

it('mewajibkan catatan saat meminta perbaikan', function (?string $catatan) {
    $this->alur->ubah($this->p, S::DalamPemeriksaan, $this->petugas);

    expect(fn () => $this->alur->ubah($this->p->refresh(), S::PerluPerbaikan, $this->petugas, $catatan))
        ->toThrow(DomainException::class, 'Catatan perbaikan wajib diisi');
})->with([null, '', '   ']);

it('petugas tidak dapat mengubah status selama menunggu perbaikan pemohon', function () {
    Skenario::jadikanPerluPerbaikan($this->p);

    expect(fn () => $this->alur->ubah($this->p->refresh(), S::DalamPemeriksaan, $this->petugas))
        ->toThrow(DomainException::class, 'menunggu perbaikan');
});

it('perubahan otomatis oleh sistem hanya untuk Perlu Perbaikan ke Dalam Pemeriksaan', function () {
    expect(fn () => $this->alur->ubah($this->p, S::DalamPemeriksaan, null))
        ->toThrow(DomainException::class, 'Petugas');

    $this->p->update(['status' => S::Diproses]);
    expect(fn () => $this->alur->ubah($this->p->refresh(), S::SiapDiambil, null))
        ->toThrow(DomainException::class, 'Petugas');
});
