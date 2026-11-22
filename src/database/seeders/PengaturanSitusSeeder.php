<?php

namespace Database\Seeders;

use App\Models\PengaturanSitus;
use Illuminate\Database\Seeder;

class PengaturanSitusSeeder extends Seeder
{
    /**
     * Nilai awal dari desain Figma. Satu baris saja, selanjutnya diedit lewat Filament.
     * Telepon dan WhatsApp sengaja kosong (di desain masih "xxxx").
     */
    public function run(): void
    {
        if (PengaturanSitus::exists()) {
            return;
        }

        PengaturanSitus::create([
            'nama_situs' => 'Sistem Pelayanan Administrasi Desa Bojong',
            'tagline' => 'Layanan Mandiri Warga Desa',
            'logo' => null,
            'foto_beranda' => null,
            'nama_instansi' => 'Pemerintah Desa Bojong',
            'alamat_kantor' => 'Jl. Raya Bojong No. 01, Kecamatan Cikupa, Kabupaten Tangerang, Banten',
            'kode_pos' => '15710',
            'telepon' => null,
            'whatsapp' => null,
            'email' => 'pelayanan@desabojong-tangerang.desa.id',
            'jam_layanan' => "Senin - Kamis: 08.00 - 15.00 WIB\nJumat: 08.00 - 11.30 & 13.00 - 15.00 WIB\nSabtu - Minggu / Hari Libur: Tutup",
            'judul_hero' => 'Selamat Datang di Pelayanan Administrasi Desa Bojong',
            'deskripsi_hero' => 'Pengajuan layanan administrasi desa kini lebih mudah melalui website. Urus dokumen kependudukan dan surat pengantar secara mandiri, transparan, dan terdata tertib.',
            'pengumuman' => 'Seluruh pelayanan pembuatan surat administrasi di Balai Desa Bojong adalah bebas biaya (gratis Rp 0,-).',
            'judul_informasi' => 'Informasi Penting Tata Cara Pelayanan',
            'isi_informasi' => 'Seluruh permohonan surat diajukan secara online melalui portal ini dan diverifikasi oleh petugas kantor desa. Surat resmi yang telah berstatus "Siap Diambil" wajib diambil secara langsung di Kantor Balai Desa Bojong dengan membawa dokumen persyaratan asli untuk verifikasi fisik akhir dan pemberian tanda tangan basah serta stempel resmi desa.',
            'langkah_alur' => [
                ['judul' => 'Pilih Jenis Layanan', 'deskripsi' => 'Tentukan jenis surat administrasi yang dibutuhkan dari daftar layanan desa sesuai kebutuhan perurusan Anda.'],
                ['judul' => 'Isi Formulir & Syarat', 'deskripsi' => 'Lengkapi data diri pemohon dan siapkan berkas persyaratan sesuai ketentuan layanan.'],
                ['judul' => 'Verifikasi Petugas', 'deskripsi' => 'Petugas pelayanan di Balai Desa Bojong memeriksa validitas data dan kelengkapan dokumen permohonan Anda.'],
                ['judul' => 'Pantau & Ambil Fisik', 'deskripsi' => 'Pantau status proses, lalu bawa dokumen asli untuk mengambil surat fisik bertanda tangan basah di Balai Desa.'],
            ],
            'catatan_pengambilan' => 'Surat berstatus "Siap Diambil" wajib diambil langsung di Balai Desa Bojong dengan membawa dokumen asli sesuai ketentuan desa. Surat diberikan dengan tanda tangan basah dan stempel resmi desa, bukan unduhan digital.',
            'teks_footer' => 'Pemerintah Desa Bojong. Hak Cipta Dilindungi Undang-Undang.',
        ]);
    }
}
