<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengaturanSitus extends Model
{
    protected $table = 'pengaturan_situs';

    protected $fillable = [
        'nama_situs', 'tagline', 'logo', 'foto_beranda',
        'nama_instansi', 'alamat_kantor', 'kode_pos', 'telepon', 'whatsapp', 'email', 'jam_layanan',
        'judul_hero', 'deskripsi_hero', 'pengumuman', 'judul_informasi', 'isi_informasi',
        'langkah_alur', 'catatan_pengambilan', 'teks_footer',
    ];

    protected $casts = [
        'langkah_alur' => 'array',
    ];

    /** Pengaturan hanya satu baris. Ambil barisnya, atau objek kosong bila belum ada. */
    public static function ambil(): self
    {
        return static::query()->first() ?? new static();
    }
}
