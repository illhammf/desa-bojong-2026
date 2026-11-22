<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

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

    private static ?self $memo = null;

    /** Pengaturan hanya satu baris. Ambil barisnya, atau objek kosong bila belum ada. */
    public static function ambil(): self
    {
        if (static::$memo !== null) {
            return static::$memo;
        }

        try {
            return static::$memo = static::query()->first() ?? new static();
        } catch (Throwable) {
            // Tabel belum ada (misalnya sebelum migrate): jangan sampai memutus halaman.
            return new static();
        }
    }

    public static function lupakan(): void
    {
        static::$memo = null;
    }
}
