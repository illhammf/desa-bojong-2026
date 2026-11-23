<?php

use App\Enums\StatusPermohonan as S;
use App\Models\Permohonan;
use App\Services\AlurStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Skenario;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Storage::fake('local');
    $this->alur = app(AlurStatusService::class);
    $this->petugas = Skenario::user('petugas');
});

function pastikanSinkron(Permohonan $p): void
{
    $p->refresh();
    $akhir = $p->riwayat()->get()->last();

    expect($akhir)->not->toBeNull();
    expect($akhir->status)->toBe($p->status);
}

it('status selalu sama dengan riwayat terakhir di setiap langkah, termasuk perbaikan berulang (NFR-29)', function () {
    $p = Skenario::ajukanSiu();
    pastikanSinkron($p);

    $langkah = [
        [S::DalamPemeriksaan, $this->petugas, null],
        [S::PerluPerbaikan, $this->petugas, 'Foto KTP buram.'],
        [S::DalamPemeriksaan, null, 'Pemohon mengirim perbaikan.'],
        [S::PerluPerbaikan, $this->petugas, 'Alamat usaha belum jelas.'],
        [S::DalamPemeriksaan, null, 'Pemohon mengirim perbaikan.'],
    ];

    foreach ($langkah as [$tujuan, $oleh, $catatan]) {
        $this->alur->ubah($p->refresh(), $tujuan, $oleh, $catatan);
        pastikanSinkron($p);
    }

    Skenario::tandaiSesuai($p);
    foreach ([S::Diproses, S::SiapDiambil] as $tujuan) {
        $this->alur->ubah($p->refresh(), $tujuan, $this->petugas);
        pastikanSinkron($p);
    }

    expect($p->riwayat()->count())->toBe(8);
});

it('menolak perubahan status langsung lewat Eloquent, tetapi mengizinkan perubahan kolom lain', function () {
    $p = Skenario::ajukanSiu();

    expect(fn () => $p->update(['status' => S::Diproses]))->toThrow(LogicException::class);
    expect(fn () => $p->forceFill(['status' => S::SiapDiambil])->save())->toThrow(LogicException::class);
    expect($p->refresh()->status)->toBe(S::Diajukan);

    $p->update(['catatan_petugas' => 'catatan biasa']);
    expect($p->refresh()->catatan_petugas)->toBe('catatan biasa');
});

it('izin ubah status hanya berlaku selama pemanggilan service dan tidak bocor, bahkan saat gagal', function () {
    $p = Skenario::ajukanSiu();

    try {
        Permohonan::denganIzinUbahStatus(function () {
            throw new RuntimeException('gagal di tengah');
        });
    } catch (RuntimeException) {
    }
    expect(fn () => $p->refresh()->update(['status' => S::Diproses]))->toThrow(LogicException::class);

    $this->alur->ubah($p->refresh(), S::DalamPemeriksaan, $this->petugas);
    expect(fn () => $p->refresh()->update(['status' => S::Diproses]))->toThrow(LogicException::class);
});

it('pengajuan baru tidak terhalang pengaman dan langsung konsisten', function () {
    $p = Skenario::ajukanSiu();

    expect($p->status)->toBe(S::Diajukan);
    pastikanSinkron($p);
});

it('perintah app:periksa-konsistensi lolos pada data sehat dan melaporkan data yang menyimpang', function () {
    $p = Skenario::ajukanSiu();
    $this->alur->ubah($p, S::DalamPemeriksaan, $this->petugas);

    $this->artisan('app:periksa-konsistensi')->assertExitCode(0);

    // rusak lewat query builder (melewati event model): status loncat tanpa riwayat
    DB::table('permohonan')->where('id', $p->id)->update(['status' => 'siap_diambil']);

    $this->artisan('app:periksa-konsistensi')
        ->expectsOutputToContain($p->nomor_permohonan)
        ->assertExitCode(1);
});
