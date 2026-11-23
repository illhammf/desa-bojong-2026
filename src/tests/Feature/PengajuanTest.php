<?php

use App\Enums\StatusPermohonan as S;
use App\Enums\StatusVerifikasiDokumen as V;
use App\Models\Permohonan;
use App\Models\Persyaratan;
use App\Models\User;
use App\Services\PengajuanService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Skenario;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Storage::fake('local');
    $this->svc = app(PengajuanService::class);
    $this->warga = Skenario::user('warga');
    $this->siu = Skenario::layanan('SIU');
});

// ---------------------------------------------------------------- pengajuan

it('menolak pengajuan kosong dan menyebut tiap bagian yang kurang', function () {
    $galat = Skenario::galat(fn () => $this->svc->ajukan($this->warga, $this->siu, '', [], []));

    foreach ([
        'alasan_pengajuan',
        'isian.' . Skenario::idSyarat('Jenis Usaha'),
        'isian.' . Skenario::idSyarat('Nama Usaha'),
        'isian.' . Skenario::idSyarat('Alamat Usaha'),
        'berkas.' . Skenario::idSyarat('KTP'),
        'berkas.' . Skenario::idSyarat('Dokumentasi Tempat Usaha'),
    ] as $kunci) {
        expect($galat)->toContain($kunci);
    }
    expect(Permohonan::count())->toBe(0);
});

it('menolak berkas berbahaya atau lebih dari 5 MB dan menerima tepat 5 MB', function () {
    $ktp = Skenario::idSyarat('KTP');
    $foto = Skenario::idSyarat('Dokumentasi Tempat Usaha');

    $galat = Skenario::galat(fn () => $this->svc->ajukan($this->warga, $this->siu, Skenario::ALASAN, Skenario::isianSiu(), [
        $ktp => Skenario::exe(),
        $foto => Skenario::pdf('besar.pdf', 5121),
    ]));
    expect($galat)->toContain("berkas.$ktp")->toContain("berkas.$foto");
    expect(Permohonan::count())->toBe(0);

    $p = $this->svc->ajukan($this->warga, $this->siu, Skenario::ALASAN, Skenario::isianSiu(), [
        $ktp => Skenario::pdf('ktp.pdf', 5120),
        $foto => Skenario::jpg(),
    ]);
    expect($p->dokumen()->count())->toBe(2);
});

it('menolak isian dan dokumen milik layanan lain', function () {
    $idIsianLain = Persyaratan::where('jenis_layanan_id', '!=', $this->siu->id)->where('tipe_persyaratan', 'isian')->value('id');
    $idDokumenLain = Persyaratan::where('jenis_layanan_id', '!=', $this->siu->id)->where('tipe_persyaratan', 'dokumen')->value('id');

    $g1 = Skenario::galat(fn () => $this->svc->ajukan($this->warga, $this->siu, Skenario::ALASAN, Skenario::isianSiu() + [$idIsianLain => 'x'], Skenario::berkasSiu()));
    $g2 = Skenario::galat(fn () => $this->svc->ajukan($this->warga, $this->siu, Skenario::ALASAN, Skenario::isianSiu(), Skenario::berkasSiu() + [$idDokumenLain => Skenario::pdf()]));

    expect($g1)->toContain('isian');
    expect($g2)->toContain('berkas');
});

it('menolak pengajuan pada layanan nonaktif', function () {
    $umm = Skenario::layanan('UMM');
    $umm->update(['aktif' => false]);

    expect(Skenario::galat(fn () => $this->svc->ajukan($this->warga, $umm, Skenario::ALASAN, [], [])))->toContain('layanan');
});

it('hanya masyarakat aktif dengan profil yang dapat mengajukan', function () {
    foreach (['petugas', 'admin', 'superadmin'] as $peran) {
        expect(fn () => $this->svc->ajukan(Skenario::user($peran), $this->siu, Skenario::ALASAN, Skenario::isianSiu(), Skenario::berkasSiu()))
            ->toThrow(AuthorizationException::class);
    }

    $this->warga->update(['status_akun' => 'nonaktif']);
    expect(fn () => $this->svc->ajukan($this->warga, $this->siu, Skenario::ALASAN, Skenario::isianSiu(), Skenario::berkasSiu()))
        ->toThrow(AuthorizationException::class);

    $tanpaProfil = Skenario::buatUser('masyarakat', 'tanpaprofil@uji.test');
    expect(fn () => $this->svc->ajukan($tanpaProfil, $this->siu, Skenario::ALASAN, Skenario::isianSiu(), Skenario::berkasSiu()))
        ->toThrow(AuthorizationException::class);

    expect(Permohonan::count())->toBe(0);
});

it('menyimpan pengajuan lengkap: nomor, status, isian, dokumen privat, dan riwayat awal', function () {
    $p = Skenario::ajukanSiu();

    expect($p->nomor_permohonan)->toBe(sprintf('REG-SIU-%d-0001', now()->year));
    expect($p->status)->toBe(S::Diajukan);
    expect($p->user_id)->toBe($this->warga->id);
    expect($p->isian()->count())->toBe(3);
    expect($p->dokumen()->count())->toBe(2);

    foreach ($p->dokumen as $d) {
        expect(Storage::disk('local')->exists($d->lokasi_file))->toBeTrue();
        expect($d->lokasi_file)->toStartWith("dokumen/{$p->id}/");
        expect($d->lokasi_file)->not->toContain('ktp')->not->toContain('foto');
        expect($d->status_verifikasi)->toBe(V::BelumDiperiksa);
    }

    $riwayat = $p->riwayat()->get();
    expect($riwayat)->toHaveCount(1);
    expect($riwayat[0]->status)->toBe(S::Diajukan);
    expect($riwayat[0]->petugas_id)->toBeNull();
});

it('kirim ganda dengan token yang sama hanya membuat satu permohonan', function () {
    $token = (string) Str::uuid();

    $a = Skenario::ajukanSiu(null, $token);
    $b = Skenario::ajukanSiu(null, $token);

    expect($b->id)->toBe($a->id);
    expect(Permohonan::count())->toBe(1);
});

it('memberi nomor berurutan tanpa duplikat', function () {
    $a = Skenario::ajukanSiu();
    $b = Skenario::ajukanSiu();

    expect($b->urut)->toBe($a->urut + 1);
    expect(Permohonan::pluck('nomor_permohonan')->duplicates())->toBeEmpty();
});

it('menyimpan nama berkas yang sudah dibersihkan dan memakai nama acak di penyimpanan', function () {
    $ktp = Skenario::idSyarat('KTP');
    $foto = Skenario::idSyarat('Dokumentasi Tempat Usaha');

    $p = $this->svc->ajukan($this->warga, $this->siu, Skenario::ALASAN, Skenario::isianSiu(), [
        $ktp => Skenario::pdf('../../evil<script>.pdf'),
        $foto => Skenario::jpg(),
    ]);

    $dokumen = $p->dokumen()->where('persyaratan_id', $ktp)->first();
    expect($dokumen->nama_file)->toBe('evilscript.pdf');
    expect($dokumen->lokasi_file)->not->toContain('evil');
});

it('tidak mewajibkan dokumen yang opsional', function () {
    $p = $this->svc->ajukan($this->warga, Skenario::layanan('UMM'), 'Keperluan fasilitas umum', [], []);

    expect($p->nomor_permohonan)->toStartWith('REG-UMM-');
    expect($p->dokumen()->count())->toBe(0);
});

it('memvalidasi isian bertipe pilihan dan menyesuaikan persyaratan dengan jenis pengurusan', function () {
    $kk = Skenario::layanan('KK');
    $jenis = Skenario::idSyarat('Jenis Pengurusan', 'KK');
    $ktp = Skenario::idSyarat('KTP', 'KK');
    $nikah = Skenario::idSyarat('Buku Nikah', 'KK');

    $galat = Skenario::galat(fn () => $this->svc->ajukan($this->warga, $kk, 'Pindah domisili keluarga', [$jenis => 'Lainnya'], [
        $ktp => Skenario::pdf(), $nikah => Skenario::pdf(),
    ]));
    expect($galat)->toContain("isian.$jenis");

    // Surat Pindah opsional (menunggu konfirmasi desa), jadi tidak diwajibkan.
    $p = $this->svc->ajukan($this->warga, $kk, 'Pindah domisili keluarga', [$jenis => 'Pindah'], [
        $ktp => Skenario::pdf(), $nikah => Skenario::pdf(),
    ]);
    expect($p->dokumen()->count())->toBe(2);
});

it('memvalidasi bentuk isian yang ditambahkan admin: angka, tanggal, dan pilihan', function () {
    $umm = Skenario::layanan('UMM');
    $buat = fn (string $nama, string $input, ?array $opsi = null) => Persyaratan::create([
        'jenis_layanan_id' => $umm->id, 'nama_persyaratan' => $nama, 'tipe_persyaratan' => 'isian',
        'tipe_input' => $input, 'opsi' => $opsi, 'wajib' => true,
    ]);
    $angka = $buat('Jumlah Tamu', 'angka');
    $tanggal = $buat('Tanggal Acara', 'tanggal');
    $pilihan = $buat('Jenis Acara', 'pilihan', ['Pernikahan', 'Khitanan']);

    $galat = Skenario::galat(fn () => $this->svc->ajukan($this->warga, $umm, 'Acara keluarga', [
        $angka->id => 'abc', $tanggal->id => 'bukan tanggal', $pilihan->id => 'Lainnya',
    ], []));
    expect($galat)->toContain("isian.{$angka->id}")->toContain("isian.{$tanggal->id}")->toContain("isian.{$pilihan->id}");

    $p = $this->svc->ajukan($this->warga, $umm, 'Acara keluarga', [
        $angka->id => '150', $tanggal->id => '2026-12-01', $pilihan->id => 'Pernikahan',
    ], []);
    expect($p->isian()->count())->toBe(3);
});

it('tidak menuntut persyaratan nonaktif dan menolak isian untuk persyaratan nonaktif', function () {
    $idNama = Skenario::idSyarat('Nama Usaha');
    Persyaratan::find($idNama)->update(['aktif' => false]);

    $isian = Skenario::isianSiu();
    unset($isian[$idNama]);
    $p = $this->svc->ajukan($this->warga, $this->siu, Skenario::ALASAN, $isian, Skenario::berkasSiu());
    expect($p->isian()->count())->toBe(2);

    $galat = Skenario::galat(fn () => $this->svc->ajukan($this->warga, $this->siu, Skenario::ALASAN, Skenario::isianSiu(), Skenario::berkasSiu()));
    expect($galat)->toContain('isian');
});

// ---------------------------------------------------------------- perbaikan

it('menolak perbaikan bila status bukan Perlu Perbaikan', function () {
    $p = Skenario::ajukanSiu();

    expect(fn () => $this->svc->perbaiki($this->warga, $p, 'x', [], []))->toThrow(DomainException::class);
});

it('menolak perbaikan oleh warga lain', function () {
    $p = Skenario::jadikanPerluPerbaikan(Skenario::ajukanSiu());
    $lain = Skenario::wargaBaru();

    expect(fn () => $this->svc->perbaiki($lain, $p, 'x', [], []))->toThrow(AuthorizationException::class);
    expect($p->refresh()->status)->toBe(S::PerluPerbaikan);
});

it('menolak perbaikan yang mengosongkan isian wajib atau membawa berkas berbahaya', function () {
    $p = Skenario::jadikanPerluPerbaikan(Skenario::ajukanSiu());
    $idNama = Skenario::idSyarat('Nama Usaha');
    $idKtp = Skenario::idSyarat('KTP');

    expect(Skenario::galat(fn () => $this->svc->perbaiki($this->warga, $p->refresh(), null, [$idNama => ''], [])))->toContain("isian.$idNama");
    expect(Skenario::galat(fn () => $this->svc->perbaiki($this->warga, $p->refresh(), null, [], [$idKtp => Skenario::exe()])))->toContain("berkas.$idKtp");
    expect($p->refresh()->status)->toBe(S::PerluPerbaikan);
});

it('memproses perbaikan sah: dokumen diganti, status kembali, nomor tetap, riwayat bertambah', function () {
    $p = Skenario::jadikanPerluPerbaikan(Skenario::ajukanSiu());
    $idKtp = Skenario::idSyarat('KTP');
    $idFoto = Skenario::idSyarat('Dokumentasi Tempat Usaha');
    $idNama = Skenario::idSyarat('Nama Usaha');
    $nomor = $p->nomor_permohonan;
    $pathLama = $p->dokumen()->where('persyaratan_id', $idKtp)->value('lokasi_file');

    $baru = $this->svc->perbaiki($this->warga, $p->refresh(), 'Sudah diunggah ulang.', [$idNama => 'Warung Uji Baru'], [$idKtp => Skenario::pdf('ktp_baru.pdf')]);

    expect($baru->status)->toBe(S::DalamPemeriksaan);
    expect($baru->nomor_permohonan)->toBe($nomor);
    expect(Permohonan::count())->toBe(1);

    $ktp = $baru->dokumen()->where('persyaratan_id', $idKtp)->get();
    expect($ktp)->toHaveCount(1);
    expect($ktp[0]->status_verifikasi)->toBe(V::BelumDiperiksa);
    expect(Storage::disk('local')->exists($pathLama))->toBeFalse();
    expect(Storage::disk('local')->exists($ktp[0]->lokasi_file))->toBeTrue();
    expect($baru->dokumen()->where('persyaratan_id', $idFoto)->first()->status_verifikasi)->toBe(V::Sesuai);
    expect($baru->isian()->where('persyaratan_id', $idNama)->value('nilai_isian'))->toBe('Warung Uji Baru');

    $riwayat = $baru->riwayat()->get();
    expect($riwayat)->toHaveCount(4);
    expect($riwayat->last()->status)->toBe(S::DalamPemeriksaan);
    expect($riwayat->last()->petugas_id)->toBeNull();
    expect($riwayat->last()->keterangan)->toContain('Sudah diunggah ulang');
});

it('menerima perbaikan yang hanya mengubah isian tanpa mengunggah ulang dokumen', function () {
    $p = Skenario::jadikanPerluPerbaikan(Skenario::ajukanSiu());

    $baru = $this->svc->perbaiki($this->warga, $p->refresh(), null, [Skenario::idSyarat('Alamat Usaha') => 'Jl. Baru No. 2'], []);

    expect($baru->status)->toBe(S::DalamPemeriksaan);
    expect($baru->dokumen()->count())->toBe(2);
});

it('menolak perbaikan kedua kali setelah status kembali ke Dalam Pemeriksaan', function () {
    $p = Skenario::jadikanPerluPerbaikan(Skenario::ajukanSiu());
    $this->svc->perbaiki($this->warga, $p->refresh(), null, [], []);

    expect(fn () => $this->svc->perbaiki($this->warga, $p->refresh(), 'ulang', [], []))->toThrow(DomainException::class);
});
