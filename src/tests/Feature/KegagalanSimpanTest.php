<?php

use App\Enums\StatusPermohonan as S;
use App\Models\Dokumen;
use App\Models\Permohonan;
use App\Models\RiwayatStatus;
use App\Services\PengajuanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Skenario;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Storage::fake('local');
});

afterEach(fn () => RiwayatStatus::flushEventListeners());

it('kegagalan di tengah pengajuan tidak meninggalkan permohonan, dokumen, maupun berkas (NFR-16)', function () {
    RiwayatStatus::creating(function () {
        throw new RuntimeException('simulasi gagal simpan');
    });

    expect(fn () => Skenario::ajukanSiu())->toThrow(RuntimeException::class);

    expect(Permohonan::count())->toBe(0);
    expect(Dokumen::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('pengajuan dapat diulang dan berhasil setelah kegagalan (kunci tidak tertahan)', function () {
    RiwayatStatus::creating(function () {
        throw new RuntimeException('simulasi gagal simpan');
    });
    expect(fn () => Skenario::ajukanSiu())->toThrow(RuntimeException::class);

    RiwayatStatus::flushEventListeners();

    $p = Skenario::ajukanSiu();

    expect($p->status)->toBe(S::Diajukan);
    expect(Permohonan::count())->toBe(1);
    expect($p->dokumen()->count())->toBe(2);
});

it('kegagalan saat mengirim perbaikan mempertahankan dokumen lama dan membuang berkas baru (NFR-16)', function () {
    $p = Skenario::jadikanPerluPerbaikan(Skenario::ajukanSiu());
    $idKtp = Skenario::idSyarat('KTP');
    $dokumenLama = $p->dokumen()->where('persyaratan_id', $idKtp)->first();

    $berkasSebelum = Storage::disk('local')->allFiles();
    sort($berkasSebelum);

    RiwayatStatus::creating(function () {
        throw new RuntimeException('simulasi gagal simpan');
    });

    expect(fn () => app(PengajuanService::class)->perbaiki(
        Skenario::user('warga'),
        $p->refresh(),
        'Sudah diperbaiki.',
        [],
        [$idKtp => Skenario::pdf('ktp_baru.pdf')]
    ))->toThrow(RuntimeException::class);

    $berkasSesudah = Storage::disk('local')->allFiles();
    sort($berkasSesudah);

    expect($berkasSesudah)->toEqual($berkasSebelum);
    expect($p->refresh()->status)->toBe(S::PerluPerbaikan);
    expect($p->dokumen()->where('persyaratan_id', $idKtp)->count())->toBe(1);
    expect($p->dokumen()->where('persyaratan_id', $idKtp)->first()->id)->toBe($dokumenLama->id);
    expect(Storage::disk('local')->exists($dokumenLama->lokasi_file))->toBeTrue();
});
