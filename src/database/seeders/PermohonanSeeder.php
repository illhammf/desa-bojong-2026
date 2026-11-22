<?php

namespace Database\Seeders;

use App\Enums\StatusPermohonan as S;
use App\Enums\StatusVerifikasiDokumen as V;
use App\Models\Dokumen;
use App\Models\IsianPermohonan;
use App\Models\JenisLayanan;
use App\Models\Permohonan;
use App\Models\Persyaratan;
use App\Models\RiwayatStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        // 1. Surat Ijin Usaha: Siap Diambil
        $siu = $this->buat($warga, $petugas, 'SIU', 1, 'Persyaratan pengajuan Kredit Usaha Rakyat (KUR).', now()->subDays(4), [
            [S::Diajukan, 'Permohonan diterima sistem.', false],
            [S::DalamPemeriksaan, 'Petugas mulai memeriksa berkas.', true],
            [S::Diproses, 'Data dan dokumen dinyatakan sesuai.', true],
            [S::SiapDiambil, 'Surat fisik siap diambil di Balai Desa Bojong.', true],
        ], [['KTP', V::Sesuai], ['Dokumentasi Tempat Usaha', V::Sesuai]]);

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
        $sktm = $this->buat($warga, $petugas, 'SKTM', 2, 'Bantuan beasiswa pendidikan putra/putri.', now()->subDays(3), [
            [S::Diajukan, 'Permohonan diterima sistem.', false],
            [S::DalamPemeriksaan, 'Petugas mulai memeriksa berkas.', true],
            [S::PerluPerbaikan, 'Dokumen pendukung terpotong dan buram.', true],
        ], [['Dokumen pendukung sesuai alasan pengajuan', V::TidakSesuai]]);
        $sktm->update(['catatan_petugas' => 'Dokumen pendukung terpotong dan terlihat buram. Silakan unggah ulang scan atau foto asli yang jelas dan terang.']);

        // 3. Domisili: Diproses (semua dokumen sudah sesuai)
        $this->buat($warga, $petugas, 'DOM', 3, 'Persyaratan kelengkapan administrasi bank / kredit.', now()->subDays(2), [
            [S::Diajukan, 'Permohonan diterima sistem.', false],
            [S::DalamPemeriksaan, 'Petugas mulai memeriksa berkas.', true],
            [S::Diproses, 'Data dan dokumen dinyatakan sesuai.', true],
        ], [['KTP', V::Sesuai], ['Kartu Keluarga (KK)', V::Sesuai], ['Surat Pengantar RT', V::Sesuai]]);

        // 4. Belum Pernah Menikah: Dalam Pemeriksaan (KK belum diperiksa, untuk uji pemblokiran Diproses)
        $this->buat($warga, $petugas, 'BMN', 4, 'Persyaratan pendaftaran kerja.', now()->subDay(), [
            [S::Diajukan, 'Permohonan diterima sistem.', false],
            [S::DalamPemeriksaan, 'Petugas mulai memeriksa berkas.', true],
        ], [['KTP', V::Sesuai], ['Kartu Keluarga (KK)', V::BelumDiperiksa]]);

        // 5. Domisili Usaha: Diajukan (belum disentuh petugas)
        $this->buat($warga, $petugas, 'DOMU', 5, 'Keperluan izin usaha.', now()->subHours(2), [
            [S::Diajukan, 'Permohonan diterima sistem.', false],
        ], [['KTP', V::BelumDiperiksa], ['Surat Pengantar RT', V::BelumDiperiksa]]);
    }

    /**
     * @param  array<int, array{0: S, 1: string, 2: bool}>  $alur
     * @param  array<int, array{0: string, 1: V}>  $dokumen
     */
    private function buat(User $warga, User $petugas, string $kode, int $urut, string $alasan, Carbon $tanggal, array $alur, array $dokumen = []): Permohonan
    {
        $layanan = JenisLayanan::where('kode', $kode)->firstOrFail();
        $tahun = $tanggal->year;

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

        foreach ($dokumen as [$namaSyarat, $statusVerifikasi]) {
            $syarat = Persyaratan::where('jenis_layanan_id', $layanan->id)->where('nama_persyaratan', $namaSyarat)->first();
            $namaFile = Str::slug($namaSyarat, '_') . '.pdf';
            $path = "dokumen/{$permohonan->id}/{$namaFile}";
            $isi = $this->pdfContoh("Contoh {$namaSyarat}");

            Storage::disk('local')->put($path, $isi);

            Dokumen::create([
                'permohonan_id' => $permohonan->id,
                'persyaratan_id' => $syarat?->id,
                'nama_file' => $namaFile,
                'lokasi_file' => $path,
                'tipe_file' => 'application/pdf',
                'ukuran_file' => strlen($isi),
                'status_verifikasi' => $statusVerifikasi,
                'tanggal_upload' => $tanggal,
            ]);
        }

        return $permohonan;
    }

    /** PDF satu halaman yang valid, hanya untuk data contoh. */
    private function pdfContoh(string $teks): string
    {
        $teks = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $teks);
        $isi = "BT /F1 14 Tf 20 70 Td ({$teks}) Tj ET";

        $objek = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length ' . strlen($isi) . " >>\nstream\n{$isi}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offset = [];
        foreach ($objek as $i => $o) {
            $offset[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n{$o}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objek) + 1) . "\n0000000000 65535 f \n";
        foreach ($offset as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }
        $pdf .= 'trailer
<< /Size ' . (count($objek) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }
}
