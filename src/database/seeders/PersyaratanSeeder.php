<?php

namespace Database\Seeders;

use App\Enums\TipePersyaratan;
use App\Models\JenisLayanan;
use App\Models\Persyaratan;
use Illuminate\Database\Seeder;

class PersyaratanSeeder extends Seeder
{
    private const MENUNGGU = 'Persyaratan menunggu konfirmasi Balai Desa Bojong, belum ditetapkan wajib.';

    public function run(): void
    {
        foreach ($this->data() as $kode => $daftar) {
            $layanan = JenisLayanan::where('kode', $kode)->first();
            if (! $layanan) {
                continue;
            }

            foreach ($daftar as $urutan => $p) {
                Persyaratan::firstOrCreate(
                    ['jenis_layanan_id' => $layanan->id, 'nama_persyaratan' => $p['nama']],
                    [
                        'tipe_persyaratan' => $p['tipe'],
                        'tipe_input' => $p['input'],
                        'opsi' => $p['opsi'],
                        'wajib' => $p['wajib'],
                        'keterangan' => $p['ket'],
                        'urutan' => $urutan + 1,
                        'aktif' => true,
                    ]
                );
            }
        }
    }

    private function dok(string $nama, bool $wajib = true, ?string $ket = null): array
    {
        return [
            'nama' => $nama, 'tipe' => TipePersyaratan::Dokumen->value,
            'input' => 'teks', 'opsi' => null, 'wajib' => $wajib, 'ket' => $ket,
        ];
    }

    private function isi(string $nama, string $input = 'teks', bool $wajib = true, ?string $ket = null, ?array $opsi = null): array
    {
        return [
            'nama' => $nama, 'tipe' => TipePersyaratan::Isian->value,
            'input' => $input, 'opsi' => $opsi, 'wajib' => $wajib, 'ket' => $ket,
        ];
    }

    /** Sumber: SRS bab 6.4.1. Yang belum jelas ditandai wajib = false. */
    private function data(): array
    {
        $ktp = fn () => $this->dok('KTP');
        $kk = fn () => $this->dok('Kartu Keluarga (KK)');
        $rt = fn () => $this->dok('Surat Pengantar RT');

        return [
            'DOMY' => [$ktp(), $rt()],
            'DOMU' => [$ktp(), $rt()],
            'KK' => [
                $this->isi('Jenis Pengurusan', 'pilihan', true, 'Menentukan persyaratan yang berlaku.', ['Pindah', 'Upgrade']),
                $ktp(),
                $this->dok('Surat Pindah', false, 'Diperlukan untuk pengurusan pindah. Validasi kondisional menyusul.'),
                $this->dok('Buku Nikah'),
            ],
            'KTP' => [$kk(), $this->dok('Ijazah Terakhir'), $rt()],
            'PND' => [$kk(), $ktp(), $rt()],
            'SKTM' => [$this->dok('Dokumen pendukung sesuai alasan pengajuan', false, self::MENUNGGU)],
            'BDN' => [$this->dok('Dokumen pendukung perbedaan nama (misalnya Buku Nikah)', false, self::MENUNGGU)],
            'MNS' => [
                $ktp(),
                $kk(),
                $this->dok('Surat Keterangan Belum Menikah'),
                $this->dok('Surat Kesehatan Kedua Calon'),
                $this->dok('KTP Orang Tua dan Wali'),
                $this->dok('Surat Pengantar Nikah dari Pihak Luar Daerah', false, 'Diperlukan sesuai kondisi.'),
            ],
            'MNB' => [$this->dok('Buku Nikah Kedua Belah Pihak')],
            'WLI' => [
                $this->dok('KTP Wali'),
                $this->dok('KTP Pihak yang Diwalikan'),
                $this->isi('Alasan Perwalian', 'area_teks'),
            ],
            'BMN' => [$ktp(), $kk()],
            'AKT' => [
                $this->dok('Surat Keterangan Lahir'),
                $this->dok('KK yang Telah Diperbarui'),
                $this->dok('Buku Nikah'),
                $this->dok('KK Orang Tua'),
            ],
            'KLR' => [$kk(), $this->dok('KTP Orang Tua')],
            'KMT' => [
                $this->dok('KTP Almarhum'),
                $this->dok('Surat Pernyataan Kematian'),
                $this->dok('Keterangan Kematian dari Rumah Sakit', false, 'Opsional, sesuai catatan desa.'),
            ],
            'WRS' => [
                $this->dok('KTP Semua Anak Kandung dan Istri'),
                $this->isi('Keterangan Anggota Keluarga yang Telah Menikah', 'area_teks', false, self::MENUNGGU),
            ],
            'BMR' => [$ktp(), $rt()],
            'KRM' => [$this->dok('Dokumen pendukung sesuai alasan pengajuan', false, self::MENUNGGU)],
            'DOM' => [$ktp(), $kk(), $rt()],
            'SIU' => [
                $ktp(),
                $this->isi('Jenis Usaha'),
                $this->isi('Nama Usaha'),
                $this->isi('Alamat Usaha', 'area_teks'),
                $this->dok('Dokumentasi Tempat Usaha'),
            ],
            'UMM' => [$this->dok('Dokumen pendukung sesuai alasan pengajuan', false, self::MENUNGGU)],
        ];
    }
}
