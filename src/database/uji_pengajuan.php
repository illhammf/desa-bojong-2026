<?php

use App\Enums\StatusPermohonan as S;
use App\Enums\StatusVerifikasiDokumen as V;
use App\Models\JenisLayanan;
use App\Models\Masyarakat;
use App\Models\Permohonan;
use App\Models\Persyaratan;
use App\Models\User;
use App\Services\AlurStatusService;
use App\Services\PengajuanService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/*
 * Uji skenario pengajuan dan perbaikan (PengajuanService + AlurStatusService).
 * Semua perubahan database dibatalkan (rollback) dan berkas memakai disk palsu.
 * Jalankan: dca tinker --execute="require base_path('database/uji_pengajuan.php');"
 */

if (! function_exists('uji_pdf')) {
    function uji_pdf(string $nama = 'berkas.pdf', int $kb = 100): UploadedFile
    {
        return UploadedFile::fake()->create($nama, $kb, 'application/pdf');
    }

    function uji_jpg(string $nama = 'foto.jpg', int $kb = 100): UploadedFile
    {
        return UploadedFile::fake()->create($nama, $kb, 'image/jpeg');
    }

    /** Kunci galat validasi, atau [] bila tidak ada galat. */
    function uji_galat(callable $f): array
    {
        try {
            $f();
        } catch (ValidationException $e) {
            return array_keys($e->errors());
        }

        return [];
    }

    /** Nama kelas pengecualian yang dilempar, atau '-' bila tidak ada. */
    function uji_kelas(callable $f): string
    {
        try {
            $f();
        } catch (Throwable $e) {
            return get_class($e);
        }

        return '-';
    }
}

$lulus = 0;
$gagal = 0;
$cek = function (string $nama, callable $uji) use (&$lulus, &$gagal): void {
    try {
        $hasil = $uji();
    } catch (Throwable $e) {
        $hasil = get_class($e) . ': ' . $e->getMessage();
    }

    if ($hasil === true) {
        $lulus++;
        echo "  [OK]    {$nama}\n";
    } else {
        $gagal++;
        echo "  [GAGAL] {$nama} => " . (is_string($hasil) ? $hasil : var_export($hasil, true)) . "\n";
    }
};

$jumlahAwal = Permohonan::count();

DB::beginTransaction();
Storage::fake('local');

try {
    $svc = app(PengajuanService::class);
    $alur = app(AlurStatusService::class);

    $warga = User::where('email', 'warga@demo.test')->firstOrFail();
    $petugas = User::where('email', 'petugas@demo.test')->firstOrFail();

    $lain = User::create(['name' => 'Warga Lain', 'email' => 'lain@uji.test', 'no_hp' => '081200000099', 'status_akun' => 'aktif', 'password' => 'password']);
    $lain->assignRole('masyarakat');
    Masyarakat::create(['user_id' => $lain->id, 'nik' => '3603190000000099', 'alamat' => 'Alamat uji']);

    $siu = JenisLayanan::where('kode', 'SIU')->firstOrFail();
    $idSyarat = fn (string $nama): int => (int) Persyaratan::where('jenis_layanan_id', $siu->id)->where('nama_persyaratan', $nama)->value('id');
    $idKtp = $idSyarat('KTP');
    $idJenis = $idSyarat('Jenis Usaha');
    $idNama = $idSyarat('Nama Usaha');
    $idAlamat = $idSyarat('Alamat Usaha');
    $idFoto = $idSyarat('Dokumentasi Tempat Usaha');

    $isian = [$idJenis => 'Perdagangan', $idNama => 'Warung Uji', $idAlamat => 'Jl. Uji No. 1'];
    $berkas = fn (): array => [$idKtp => uji_pdf('ktp.pdf'), $idFoto => uji_jpg('foto.jpg')];
    $alasan = 'Persyaratan pengajuan KUR (uji).';
    $urutAwal = (int) Permohonan::where('tahun', now()->year)->max('urut');

    $p1 = null;
    $pA = null;

    echo "\nUJI PENGAJUAN\n";

    $cek('1. Pengajuan kosong ditolak: alasan, 3 isian wajib, 2 dokumen wajib', function () use ($svc, $warga, $siu, $idJenis, $idNama, $idAlamat, $idKtp, $idFoto) {
        $k = uji_galat(fn () => $svc->ajukan($warga, $siu, '', [], []));
        foreach (['alasan_pengajuan', "isian.$idJenis", "isian.$idNama", "isian.$idAlamat", "berkas.$idKtp", "berkas.$idFoto"] as $w) {
            if (! in_array($w, $k, true)) {
                return "tidak ada galat untuk $w (galat: " . implode(', ', $k) . ')';
            }
        }

        return true;
    });

    $cek('2. Berkas .exe dan berkas 6 MB ditolak', function () use ($svc, $warga, $siu, $isian, $alasan, $idKtp, $idFoto) {
        $k = uji_galat(fn () => $svc->ajukan($warga, $siu, $alasan, $isian, [
            $idKtp => UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload'),
            $idFoto => uji_pdf('besar.pdf', 6000),
        ]));

        return (in_array("berkas.$idKtp", $k, true) && in_array("berkas.$idFoto", $k, true)) ? true : 'galat: ' . implode(', ', $k);
    });

    $cek('3. Isian milik layanan lain ditolak', function () use ($svc, $warga, $siu, $isian, $berkas, $alasan) {
        $idLain = Persyaratan::where('jenis_layanan_id', '!=', $siu->id)->value('id');
        $k = uji_galat(fn () => $svc->ajukan($warga, $siu, $alasan, $isian + [$idLain => 'x'], $berkas()));

        return in_array('isian', $k, true) ?: 'galat: ' . implode(', ', $k);
    });

    $cek('4. Layanan nonaktif tidak dapat diajukan', function () use ($svc, $warga, $alasan) {
        $umm = JenisLayanan::where('kode', 'UMM')->firstOrFail();
        $umm->update(['aktif' => false]);

        return in_array('layanan', uji_galat(fn () => $svc->ajukan($warga, $umm, $alasan, [], [])), true) ?: 'tidak ditolak';
    });

    $cek('5. Petugas tidak dapat mengajukan permohonan', function () use ($svc, $petugas, $siu, $isian, $berkas, $alasan) {
        $kelas = uji_kelas(fn () => $svc->ajukan($petugas, $siu, $alasan, $isian, $berkas()));

        return $kelas === AuthorizationException::class ?: "dilempar: $kelas";
    });

    $cek('6. Pengajuan lengkap: nomor, status, 3 isian, 2 dokumen privat, 1 riwayat', function () use ($svc, $warga, $siu, $isian, $berkas, $alasan, $urutAwal, &$p1) {
        $p1 = $svc->ajukan($warga, $siu, $alasan, $isian, $berkas());
        $dok = $p1->dokumen()->get();
        $riw = $p1->riwayat()->get();

        if (! preg_match('/^REG-SIU-' . now()->year . '-\d{4,}$/', $p1->nomor_permohonan)) {
            return 'format nomor salah: ' . $p1->nomor_permohonan;
        }
        if ((int) $p1->urut !== $urutAwal + 1) {
            return "urut {$p1->urut}, seharusnya " . ($urutAwal + 1);
        }
        if ($p1->status !== S::Diajukan) {
            return 'status awal bukan Diajukan';
        }
        if ($p1->isian()->count() !== 3) {
            return 'jumlah isian ' . $p1->isian()->count();
        }
        if ($dok->count() !== 2) {
            return 'jumlah dokumen ' . $dok->count();
        }
        foreach ($dok as $d) {
            if (! Storage::disk('local')->exists($d->lokasi_file)) {
                return "berkas tidak ada: {$d->lokasi_file}";
            }
            if ($d->status_verifikasi !== V::BelumDiperiksa) {
                return 'status verifikasi awal salah';
            }
            if (str_contains($d->lokasi_file, 'ktp') || str_contains($d->lokasi_file, 'foto')) {
                return 'nama berkas asli bocor ke path penyimpanan';
            }
        }
        if ($riw->count() !== 1 || $riw[0]->status !== S::Diajukan || $riw[0]->petugas_id !== null) {
            return 'riwayat awal salah';
        }

        return true;
    });

    $cek('7. Kirim ganda dengan token yang sama hanya membuat satu permohonan', function () use ($svc, $warga, $siu, $isian, $berkas, $alasan, &$pA) {
        $token = (string) Str::uuid();
        $sebelum = Permohonan::count();
        $a = $svc->ajukan($warga, $siu, $alasan, $isian, $berkas(), $token);
        $b = $svc->ajukan($warga, $siu, $alasan, $isian, $berkas(), $token);
        $pA = $a;

        if ($a->id !== $b->id) {
            return 'menghasilkan dua permohonan berbeda';
        }

        return Permohonan::count() === $sebelum + 1 ?: 'jumlah bertambah ' . (Permohonan::count() - $sebelum);
    });

    $cek('8. Nomor berurutan dan tidak ada duplikat', function () use (&$p1, &$pA) {
        if (! $p1 || ! $pA) {
            return 'prasyarat (uji 6 dan 7) gagal';
        }
        if ((int) $pA->urut !== (int) $p1->urut + 1) {
            return "urut {$pA->urut} setelah {$p1->urut}";
        }

        return Permohonan::pluck('nomor_permohonan')->duplicates()->isEmpty() ?: 'ada nomor ganda';
    });

    echo "\nUJI PERBAIKAN\n";

    if (! $p1) {
        echo "  Pengajuan dasar gagal, uji perbaikan dilewati.\n";
    } else {
        $cek('9. Perbaikan ditolak bila status bukan Perlu Perbaikan', function () use ($svc, $warga, &$p1) {
            $kelas = uji_kelas(fn () => $svc->perbaiki($warga, $p1->refresh(), 'x', [], []));

            return $kelas === DomainException::class ?: "dilempar: $kelas";
        });

        // Petugas memeriksa: KTP tidak sesuai, foto sesuai, lalu minta perbaikan.
        $alur->ubah($p1->refresh(), S::DalamPemeriksaan, $petugas);
        $p1->dokumen()->where('persyaratan_id', $idKtp)->update(['status_verifikasi' => V::TidakSesuai->value]);
        $p1->dokumen()->where('persyaratan_id', $idFoto)->update(['status_verifikasi' => V::Sesuai->value]);
        $alur->ubah($p1->refresh(), S::PerluPerbaikan, $petugas, 'Foto KTP buram, mohon unggah ulang.');
        $pathKtpLama = (string) $p1->dokumen()->where('persyaratan_id', $idKtp)->value('lokasi_file');

        $cek('10. Warga lain tidak dapat memperbaiki permohonan orang lain', function () use ($svc, $lain, &$p1) {
            $kelas = uji_kelas(fn () => $svc->perbaiki($lain, $p1->refresh(), 'x', [], []));

            return $kelas === AuthorizationException::class ?: "dilempar: $kelas";
        });

        $cek('11. Perbaikan mengosongkan isian wajib ditolak', function () use ($svc, $warga, &$p1, $idNama) {
            $k = uji_galat(fn () => $svc->perbaiki($warga, $p1->refresh(), null, [$idNama => ''], []));

            return in_array("isian.$idNama", $k, true) ?: 'galat: ' . implode(', ', $k);
        });

        $cek('12. Perbaikan dengan berkas .exe ditolak', function () use ($svc, $warga, &$p1, $idKtp) {
            $k = uji_galat(fn () => $svc->perbaiki($warga, $p1->refresh(), null, [], [
                $idKtp => UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload'),
            ]));

            return in_array("berkas.$idKtp", $k, true) ?: 'galat: ' . implode(', ', $k);
        });

        $cek('13. Perbaikan sah: dokumen diganti, status kembali, nomor tetap, riwayat bertambah', function () use ($svc, $warga, &$p1, $idKtp, $idFoto, $idNama, $pathKtpLama) {
            $jumlah = Permohonan::count();
            $nomor = $p1->nomor_permohonan;

            $baru = $svc->perbaiki($warga, $p1->refresh(), 'Sudah diunggah ulang.', [$idNama => 'Warung Uji Baru'], [$idKtp => uji_pdf('ktp_baru.pdf')]);

            $ktp = $baru->dokumen()->where('persyaratan_id', $idKtp)->get();
            $foto = $baru->dokumen()->where('persyaratan_id', $idFoto)->first();
            $riw = $baru->riwayat()->get();
            $akhir = $riw->last();

            if ($baru->status !== S::DalamPemeriksaan) {
                return 'status ' . $baru->status->value;
            }
            if ($baru->nomor_permohonan !== $nomor || Permohonan::count() !== $jumlah) {
                return 'perbaikan membuat permohonan baru atau mengubah nomor';
            }
            if ($ktp->count() !== 1) {
                return 'dokumen KTP berjumlah ' . $ktp->count();
            }
            if ($ktp[0]->status_verifikasi !== V::BelumDiperiksa) {
                return 'dokumen pengganti belum berstatus Belum Diperiksa';
            }
            if (Storage::disk('local')->exists($pathKtpLama)) {
                return 'berkas lama masih tersimpan';
            }
            if (! Storage::disk('local')->exists($ktp[0]->lokasi_file)) {
                return 'berkas baru tidak tersimpan';
            }
            if ($foto->status_verifikasi !== V::Sesuai) {
                return 'dokumen yang tidak diganti ikut berubah';
            }
            if ($baru->isian()->where('persyaratan_id', $idNama)->value('nilai_isian') !== 'Warung Uji Baru') {
                return 'isian tidak diperbarui';
            }
            if ($riw->count() !== 4) {
                return 'riwayat berjumlah ' . $riw->count() . ' (seharusnya 4)';
            }
            if ($akhir->status !== S::DalamPemeriksaan || $akhir->petugas_id !== null || ! str_contains((string) $akhir->keterangan, 'Sudah diunggah ulang')) {
                return 'riwayat terakhir tidak sesuai';
            }

            return true;
        });

        $cek('14. Perbaikan kedua kali (status sudah Dalam Pemeriksaan) ditolak', function () use ($svc, $warga, &$p1) {
            $kelas = uji_kelas(fn () => $svc->perbaiki($warga, $p1->refresh(), 'ulang', [], []));

            return $kelas === DomainException::class ?: "dilempar: $kelas";
        });

        $cek('15. Sistem (tanpa petugas) tidak dapat memindahkan status ke Diproses', function () use ($alur, &$p1) {
            $p1->dokumen()->update(['status_verifikasi' => V::Sesuai->value]);

            try {
                $alur->ubah($p1->refresh(), S::Diproses, null);

                return 'tidak ditolak';
            } catch (DomainException $e) {
                return str_contains($e->getMessage(), 'Petugas') ?: 'pesan tidak sesuai: ' . $e->getMessage();
            }
        });
    }
} finally {
    DB::rollBack();
    File::deleteDirectory(storage_path('framework/testing'));
}

echo "\nHasil: {$lulus} lulus, {$gagal} gagal.\n";
echo 'Rollback: permohonan ' . Permohonan::count() . " (awal {$jumlahAwal})\n";
