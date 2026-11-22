<?php

namespace Database\Seeders;

use App\Models\JenisLayanan;
use Illuminate\Database\Seeder;

class JenisLayananSeeder extends Seeder
{
    /**
     * 20 layanan administrasi Balai Desa Bojong (SRS 6.4.1).
     * firstOrCreate: perubahan yang dibuat Admin lewat Filament tidak tertimpa.
     */
    public const DAFTAR = [
        ['DOMY', 'Surat Keterangan Domisili Yayasan'],
        ['DOMU', 'Surat Keterangan Domisili Usaha'],
        ['KK',   'Surat Pembuatan KK'],
        ['KTP',  'Surat Pembuatan KTP'],
        ['PND',  'Surat Keterangan Pindah'],
        ['SKTM', 'Surat Keterangan Tidak Mampu'],
        ['BDN',  'Surat Keterangan Beda Nama KTP/KK'],
        ['MNS',  'Surat Keterangan Menikah Secara Negeri'],
        ['MNB',  'Surat Keterangan Menikah (Buku Nikah)'],
        ['WLI',  'Surat Keterangan Wali Nikah'],
        ['BMN',  'Surat Keterangan Belum Pernah Menikah'],
        ['AKT',  'Surat Pembuatan Akte Kelahiran'],
        ['KLR',  'Surat Keterangan Kelahiran'],
        ['KMT',  'Surat Keterangan Kematian'],
        ['WRS',  'Surat Keterangan Ahli Waris'],
        ['BMR',  'Surat Keterangan Belum Memiliki Rumah'],
        ['KRM',  'Surat Keterangan Ijin Keramaian'],
        ['DOM',  'Surat Keterangan Domisili'],
        ['SIU',  'Surat Ijin Usaha'],
        ['UMM',  'Surat Keterangan Umum'],
    ];

    public function run(): void
    {
        foreach (self::DAFTAR as [$kode, $nama]) {
            JenisLayanan::firstOrCreate(
                ['kode' => $kode],
                [
                    'nama_layanan' => $nama,
                    'deskripsi' => "Permohonan {$nama} di Balai Desa Bojong.",
                    'estimasi_waktu' => null,
                    'aktif' => true,
                ]
            );
        }
    }
}
