<?php

namespace Tests\Support;

use App\Enums\StatusPermohonan as S;
use App\Enums\StatusVerifikasiDokumen as V;
use App\Models\JenisLayanan;
use App\Models\Masyarakat;
use App\Models\Permohonan;
use App\Models\Persyaratan;
use App\Models\User;
use App\Services\AlurStatusService;
use App\Services\PengajuanService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/** Pembantu tes: akun demo, berkas palsu, dan skenario pengajuan Surat Ijin Usaha (SIU). */
class Skenario
{
    public const ALASAN = 'Persyaratan pengajuan KUR (uji).';

    /** $peran: superadmin, admin, petugas, warga (akun dari UserSeeder). */
    public static function user(string $peran): User
    {
        return User::where('email', "{$peran}@demo.test")->firstOrFail();
    }

    public static function buatUser(string $peran, string $email, string $status = 'aktif'): User
    {
        $user = User::create([
            'name' => ucfirst($peran) . ' Uji',
            'email' => $email,
            'no_hp' => '081200000077',
            'status_akun' => $status,
            'password' => 'password',
        ]);
        $user->assignRole($peran);

        return $user;
    }

    public static function wargaBaru(string $nik = '3603190000000099'): User
    {
        $user = self::buatUser('masyarakat', "warga{$nik}@uji.test");
        Masyarakat::create(['user_id' => $user->id, 'nik' => $nik, 'alamat' => 'Alamat uji']);

        return $user;
    }

    public static function pdf(string $nama = 'berkas.pdf', int $kb = 100): UploadedFile
    {
        return UploadedFile::fake()->create($nama, $kb, 'application/pdf');
    }

    public static function jpg(string $nama = 'foto.jpg', int $kb = 100): UploadedFile
    {
        return UploadedFile::fake()->create($nama, $kb, 'image/jpeg');
    }

    public static function exe(): UploadedFile
    {
        return UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload');
    }

    public static function layanan(string $kode): JenisLayanan
    {
        return JenisLayanan::where('kode', $kode)->firstOrFail();
    }

    public static function idSyarat(string $nama, string $kode = 'SIU'): int
    {
        return (int) Persyaratan::where('jenis_layanan_id', self::layanan($kode)->id)
            ->where('nama_persyaratan', $nama)
            ->value('id');
    }

    /** @return array<int, string> */
    public static function isianSiu(): array
    {
        return [
            self::idSyarat('Jenis Usaha') => 'Perdagangan',
            self::idSyarat('Nama Usaha') => 'Warung Uji',
            self::idSyarat('Alamat Usaha') => 'Jl. Uji No. 1',
        ];
    }

    /** @return array<int, UploadedFile> */
    public static function berkasSiu(): array
    {
        return [
            self::idSyarat('KTP') => self::pdf('ktp.pdf'),
            self::idSyarat('Dokumentasi Tempat Usaha') => self::jpg('foto.jpg'),
        ];
    }

    public static function ajukanSiu(?User $warga = null, ?string $token = null): Permohonan
    {
        return app(PengajuanService::class)->ajukan(
            $warga ?? self::user('warga'),
            self::layanan('SIU'),
            self::ALASAN,
            self::isianSiu(),
            self::berkasSiu(),
            $token
        );
    }

    /** Petugas memeriksa, KTP Tidak Sesuai, foto Sesuai, lalu meminta perbaikan. */
    public static function jadikanPerluPerbaikan(Permohonan $p, ?User $petugas = null): Permohonan
    {
        $petugas ??= self::user('petugas');
        $alur = app(AlurStatusService::class);

        $alur->ubah($p->refresh(), S::DalamPemeriksaan, $petugas);
        $p->dokumen()->where('persyaratan_id', self::idSyarat('KTP'))->update(['status_verifikasi' => V::TidakSesuai->value]);
        $p->dokumen()->where('persyaratan_id', self::idSyarat('Dokumentasi Tempat Usaha'))->update(['status_verifikasi' => V::Sesuai->value]);

        return $alur->ubah($p->refresh(), S::PerluPerbaikan, $petugas, 'Foto KTP buram, mohon unggah ulang.');
    }

    public static function tandaiSesuai(Permohonan $p): void
    {
        $p->dokumen()->update(['status_verifikasi' => V::Sesuai->value]);
    }

    /** Mengatur status lewat query builder (melewati pengaman model), khusus menyiapkan skenario tes. */
    public static function paksaStatus(Permohonan $p, S $status): void
    {
        DB::table('permohonan')->where('id', $p->id)->update(['status' => $status->value]);
        $p->refresh();
    }

    /** Kunci galat validasi yang dilempar, atau [] bila tidak ada. */
    public static function galat(callable $f): array
    {
        try {
            $f();
        } catch (ValidationException $e) {
            return array_keys($e->errors());
        }

        return [];
    }

    public static function logTerakhir(string $log, string $event): ?Activity
    {
        return Activity::where('log_name', $log)->where('event', $event)->orderByDesc('id')->first();
    }
}
