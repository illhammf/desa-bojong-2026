<?php

namespace Database\Seeders;

use App\Enums\StatusPermohonan as S;
use App\Models\IsianPermohonan;
use App\Models\JenisLayanan;
use App\Models\Permohonan;
use App\Models\Persyaratan;
use App\Models\RiwayatStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PermohonanSeeder extends Seeder
{
    /** Data contoh pengembangan, bukan untuk produksi. */
    public function run(): void
    {
        $warga = User::where('email', 'warga@demo.test')->first();
        $petugas = User::where('email', 'petugas@demo.test')->first();

        if (! $warga || ! $petugas || Permohonan::exists()) {
            return;
        }

        $tahun = now()->year;

        // 1. Surat Ijin Usaha: Siap Diambil
        $siu = $this->buat($warga, 'SIU', $tahun, 1, 'Persyaratan pengajuan Kredit Usaha Rakyat (KUR).', now()->subDays(4), [
            [S::Diajukan, 'Permohonan diterima sistem.', false],
            [S::DalamPemeriksaan, 'Petugas mulai memeriksa berkas.', true],
            [S::Diproses, 'Data dan dokumen sesuai.', true],
            [S::SiapDiambil, 'Surat fisik siap diambil di Balai Desa Bojong.', true],
        ], $petugas);

        $layananSiu = JenisLayanan::where('kode', 'SIU')->first();
        foreach ([
            'Jenis Usaha' => 'Perdagangan / Toko Kelontong & Sembako',
            'Nama Usaha' => 'Warung Sembako Berkah Subagja',
            'Alamat Usaha' => 'Jl. Kp. Bojong No. 14, RT 003 / RW 002, Desa Bojong, Kec. Cikupa',
        ] as $nama => $nilai) {
            $syarat = Persyaratan::where('jenis_layanan_id', $layananSiu->id)->where('nama_persyaratan', $nama)->first();
            if ($syarat) {
                IsianPermohonan::create(['permohonan_id' => $siu->id, 'persyaratan_id' => $syarat->id, 'nilai_isian' => $nilai]);
            }
        }

        // 2. SKTM: Perlu Perbaikan
        $sktm = $this->buat($warga, 'SKTM', $tahun, 2, 'Bantuan beasiswa pendidikan putra/putri.', now()->subDays(3), [
            [S::Diajukan, 'Permohonan diterima sistem.', false],
            [S::DalamPemeriksaan, 'Petugas mulai memeriksa berkas.', true],
            [S::PerluPerbaikan, 'Foto KK terpotong dan buram.', true],
        ], $petugas);
        $sktm->update(['catatan_petugas' => 'Foto Kartu Keluarga (KK) terpotong pada bagian nomor dan terlihat buram. Silakan unggah ulang scan atau foto KK asli yang jelas dan terang.']);

        // 3. Domisili: Diproses
        $this->buat($warga, 'DOM', $tahun, 3, 'Persyaratan kelengkapan administrasi bank / kredit.', now()->subDays(2), [
            [S::Diajukan, 'Permohonan diterima sistem.', false],
            [S::DalamPemeriksaan, 'Petugas mulai memeriksa berkas.', true],
            [S::Diproses, 'Data dan dokumen sesuai.', true],
        ], $petugas);
    }

    /** @param array<int, array{0: S, 1: string, 2: bool}> $alur */
    private function buat(User $warga, string $kode, int $tahun, int $urut, string $alasan, Carbon $tanggal, array $alur, User $petugas): Permohonan
    {
        $layanan = JenisLayanan::where('kode', $kode)->firstOrFail();

        $permohonan = Permohonan::create([
            'user_id' => $warga->id,
            'jenis_layanan_id' => $layanan->id,
            'nomor_permohonan' => sprintf('REG-%s-%d-%04d', $kode, $tahun, $urut),
            'tahun' => $tahun,
            'urut' => $urut,
            'tanggal_pengajuan' => $tanggal,
            'alasan_pengajuan' => $alasan,
            'status' => end($alur)[0],
        ]);

        foreach ($alur as $i => [$status, $keterangan, $olehPetugas]) {
            RiwayatStatus::create([
                'permohonan_id' => $permohonan->id,
                'petugas_id' => $olehPetugas ? $petugas->id : null,
                'status' => $status,
                'keterangan' => $keterangan,
                'waktu_perubahan' => $tanggal->copy()->addMinutes($i * 30),
            ]);
        }

        return $permohonan;
    }
}
