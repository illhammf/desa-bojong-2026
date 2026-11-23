<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class PeriksaProduksi extends Command
{
    protected $signature = 'app:periksa-produksi';

    protected $description = 'Periksa kesiapan pengaturan untuk produksi (NFR-06, NFR-19). Gagal bila ada pengaturan yang tidak aman.';

    private const SANDI_BAWAAN = ['', 'password', 'root', 'secret', 'admin', '12345678', 'p455w0rd'];

    private int $jumlahGagal = 0;

    private int $jumlahPeringatan = 0;

    /** Kata sandi basis data dianggap lemah bila nilai bawaan yang umum atau kurang dari 12 karakter. */
    public static function sandiLemah(string $sandi): bool
    {
        return in_array(strtolower($sandi), self::SANDI_BAWAAN, true) || strlen($sandi) < 12;
    }

    public function handle(): int
    {
        $this->line('PEMERIKSAAN KESIAPAN PRODUKSI');
        $this->newLine();

        $this->periksaAplikasi();
        $this->periksaBasisData();
        $this->periksaSesi();
        $this->periksaAkun();

        $this->newLine();

        if ($this->jumlahGagal > 0) {
            $this->error("TIDAK SIAP: {$this->jumlahGagal} pengaturan harus diperbaiki ({$this->jumlahPeringatan} peringatan).");

            return self::FAILURE;
        }

        $this->info("SIAP: tidak ada pengaturan yang menghalangi produksi ({$this->jumlahPeringatan} peringatan).");

        return self::SUCCESS;
    }

    private function ok(string $pesan): void
    {
        $this->line("  [OK]         {$pesan}");
    }

    private function lewat(string $pesan): void
    {
        $this->line("  [LEWAT]      {$pesan}");
    }

    private function gagal(string $pesan): void
    {
        $this->jumlahGagal++;
        $this->line("  [GAGAL]      {$pesan}");
    }

    private function peringatan(string $pesan): void
    {
        $this->jumlahPeringatan++;
        $this->line("  [PERINGATAN] {$pesan}");
    }

    private function periksaAplikasi(): void
    {
        if (config('app.env') === 'production') {
            $this->ok('APP_ENV = production');
        } else {
            $this->gagal('APP_ENV harus production (saat ini: ' . config('app.env') . ')');
        }

        if (config('app.debug')) {
            $this->gagal('APP_DEBUG harus false: mode debug menampilkan rincian teknis kepada pengunjung (NFR-19)');
        } else {
            $this->ok('APP_DEBUG = false');
        }

        if (blank(config('app.key'))) {
            $this->gagal('APP_KEY belum diisi');
        } else {
            $this->ok('APP_KEY terisi');
        }

        $url = (string) config('app.url');
        if (str_starts_with($url, 'https://')) {
            $this->ok('APP_URL memakai HTTPS');
        } else {
            $this->gagal("APP_URL harus diawali https:// (NFR-06), saat ini: {$url}");
        }

        if (config('app.timezone') !== 'Asia/Jakarta' || config('app.locale') !== 'id') {
            $this->peringatan('APP_TIMEZONE sebaiknya Asia/Jakarta dan APP_LOCALE id');
        }

        $kanal = (string) config('logging.default');
        $anak = config("logging.channels.{$kanal}.channels");
        $dipakai = is_array($anak) && $anak ? $anak[0] : $kanal;
        $level = config("logging.channels.{$dipakai}.level");

        if ($level === 'debug') {
            $this->peringatan('LOG_LEVEL sebaiknya warning atau error di produksi (saat ini debug)');
        } elseif ($level) {
            $this->ok("LOG_LEVEL = {$level}");
        }
    }

    private function periksaBasisData(): void
    {
        $koneksi = (string) config('database.default');
        $konfigurasi = (array) config("database.connections.{$koneksi}");

        if (($konfigurasi['driver'] ?? '') === 'sqlite') {
            $this->lewat('basis data sqlite: pemeriksaan kredensial dilewati');

            return;
        }

        if (($konfigurasi['username'] ?? '') === 'root') {
            $this->peringatan('Aplikasi memakai pengguna root basis data; buat pengguna khusus dengan hak terbatas');
        } else {
            $this->ok('Aplikasi tidak memakai pengguna root basis data');
        }

        if (self::sandiLemah((string) ($konfigurasi['password'] ?? ''))) {
            $this->gagal('Kata sandi basis data lemah atau bawaan (minimal 12 karakter dan bukan nilai bawaan yang umum)');
        } else {
            $this->ok('Kata sandi basis data tidak tergolong lemah');
        }
    }

    private function periksaSesi(): void
    {
        if (config('session.secure') === true) {
            $this->ok('Cookie sesi hanya dikirim lewat HTTPS');
        } else {
            $this->gagal('SESSION_SECURE_COOKIE harus true agar cookie sesi hanya dikirim lewat HTTPS (NFR-06)');
        }

        if (config('session.http_only')) {
            $this->ok('Cookie sesi tidak dapat dibaca JavaScript');
        } else {
            $this->gagal('session.http_only harus true');
        }
    }

    private function periksaAkun(): void
    {
        $demo = User::query()->where('email', 'like', '%@demo.test')->count();

        if ($demo > 0) {
            $this->gagal("Akun demo masih ada ({$demo}): hapus sebelum produksi");
        } else {
            $this->ok('Tidak ada akun demo');
        }

        $diperiksa = 0;
        $lemah = 0;

        User::query()->select(['id', 'password'])->orderBy('id')->limit(500)->get()->each(function (User $user) use (&$diperiksa, &$lemah) {
            $diperiksa++;

            try {
                if (Hash::check('password', (string) $user->password)) {
                    $lemah++;
                }
            } catch (\Throwable) {
                // Hash bukan bcrypt: tidak mungkin berupa kata sandi bawaan ini.
            }
        });

        if ($lemah > 0) {
            $this->gagal("{$lemah} akun masih memakai kata sandi \"password\"");
        } else {
            $this->ok("{$diperiksa} akun diperiksa: tidak ada yang memakai kata sandi bawaan");
        }

        $superAdmin = User::role('super_admin')->where('status_akun', 'aktif')->count();

        if ($superAdmin < 1) {
            $this->gagal('Tidak ada Super Admin aktif');
        } else {
            $this->ok("Super Admin aktif: {$superAdmin}");
        }
    }
}
