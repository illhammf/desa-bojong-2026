<?php

namespace App\Services;

use App\Enums\StatusPermohonan;
use App\Enums\StatusVerifikasiDokumen;
use App\Enums\TipePersyaratan;
use App\Models\Dokumen;
use App\Models\IsianPermohonan;
use App\Models\JenisLayanan;
use App\Models\Permohonan;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Pengajuan dan perbaikan permohonan oleh masyarakat
 * (BRD: FR-07 s.d. FR-10, FR-15, FR-33 s.d. FR-40; PRD 7.4 s.d. 7.8).
 * Semua aturan ada di sini agar tampilan (Blade/Livewire) cukup memanggil service ini.
 */
class PengajuanService
{
    public const MAKS_KB = 5120; // 5 MB per berkas

    /** Jenis berkas yang diizinkan, ditentukan dari isi berkas. */
    private const MIME_EKSTENSI = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    private const PESAN_UMUM = [
        'required' => ':attribute wajib diisi.',
        'string' => ':attribute harus berupa teks.',
        'numeric' => ':attribute harus berupa angka.',
        'date' => ':attribute harus berupa tanggal yang valid.',
        'in' => ':attribute tidak valid.',
        'min.string' => ':attribute minimal :min karakter.',
        'max.string' => ':attribute maksimal :max karakter.',
        'file' => ':attribute harus berupa berkas.',
        'mimes' => ':attribute harus berformat PDF, JPG, JPEG, atau PNG.',
        'max.file' => ':attribute tidak boleh lebih dari 5 MB.',
        'uploaded' => ':attribute gagal diunggah. Periksa ukuran berkas dan koneksi, lalu coba lagi.',
    ];

    public function __construct(private AlurStatusService $alur)
    {
    }

    /**
     * @param  array<int|string, mixed>  $isian  [persyaratan_id => nilai]
     * @param  array<int|string, mixed>  $berkas  [persyaratan_id => UploadedFile]
     * @param  string|null  $token  UUID yang dibuat saat form ditampilkan, mencegah kirim ganda
     */
    public function ajukan(User $pemohon, JenisLayanan $layanan, ?string $alasan, array $isian, array $berkas, ?string $token = null): Permohonan
    {
        $this->pastikanMasyarakat($pemohon);

        $kunciToken = $token ? "pengajuan:token:{$token}" : null;
        if ($kunciToken && ($sudah = $this->dariToken($kunciToken, $pemohon))) {
            return $sudah;
        }

        $berkas = $this->hanyaBerkas($berkas);
        $isian = $this->rapikanIsian($isian);
        $alasan = $alasan === null ? null : trim($alasan);

        try {
            return Cache::lock("pengajuan:pengguna:{$pemohon->id}", 20)->block(10, function () use ($pemohon, $layanan, $alasan, $isian, $berkas, $kunciToken) {
                if ($kunciToken && ($sudah = $this->dariToken($kunciToken, $pemohon))) {
                    return $sudah;
                }

                $layanan = JenisLayanan::findOrFail($layanan->id);
                if (! $layanan->aktif) {
                    throw ValidationException::withMessages(['layanan' => 'Layanan ini sedang tidak tersedia untuk pengajuan.']);
                }

                $persyaratan = $this->persyaratanAktif($layanan);
                $this->validasi($persyaratan, $alasan, $isian, $berkas);

                $permohonan = $this->simpanBaru($pemohon, $layanan, $alasan, $persyaratan, $isian, $berkas);

                if ($kunciToken) {
                    Cache::put($kunciToken, $permohonan->id, now()->addMinutes(30));
                }

                return $permohonan;
            });
        } catch (LockTimeoutException) {
            throw new DomainException('Permintaan sebelumnya masih diproses. Silakan coba lagi sebentar lagi.');
        }
    }

    /**
     * Perbaikan permohonan berstatus Perlu Perbaikan. Nomor pengajuan tetap, bukan permohonan baru.
     *
     * @param  array<int|string, mixed>  $isian  hanya isian yang diubah
     * @param  array<int|string, mixed>  $berkas  hanya dokumen pengganti
     */
    public function perbaiki(User $pemohon, Permohonan $permohonan, ?string $keterangan, array $isian, array $berkas): Permohonan
    {
        $this->pastikanMasyarakat($pemohon);

        if ($permohonan->user_id !== $pemohon->id) {
            throw new AuthorizationException('Anda hanya dapat memperbaiki permohonan milik sendiri.');
        }

        $berkas = $this->hanyaBerkas($berkas);
        $isian = $this->rapikanIsian($isian);
        $keterangan = filled($keterangan) ? trim($keterangan) : null;

        if ($keterangan !== null && mb_strlen($keterangan) > 500) {
            throw ValidationException::withMessages(['keterangan' => 'Keterangan maksimal 500 karakter.']);
        }

        try {
            return Cache::lock("pengajuan:perbaikan:{$permohonan->id}", 20)->block(10, function () use ($permohonan, $keterangan, $isian, $berkas) {
                $baru = [];
                $lama = [];

                try {
                    $hasil = DB::transaction(function () use ($permohonan, $keterangan, $isian, $berkas, &$baru, &$lama) {
                        $p = Permohonan::query()->lockForUpdate()->findOrFail($permohonan->id);

                        if ($p->status !== StatusPermohonan::PerluPerbaikan) {
                            throw new DomainException('Permohonan hanya dapat diperbaiki saat berstatus Perlu Perbaikan.');
                        }

                        $persyaratan = $this->persyaratanAktif($p->layanan);
                        $isianAda = $p->isian()->pluck('nilai_isian', 'persyaratan_id')->all();
                        $dokumenAda = $p->dokumen()->whereNotNull('persyaratan_id')->pluck('persyaratan_id')->all();

                        $this->validasi($persyaratan, null, $isian, $berkas, $isianAda, $dokumenAda, false);

                        $this->simpanIsian($p, $isian);

                        foreach ($berkas as $idSyarat => $file) {
                            foreach ($p->dokumen()->where('persyaratan_id', $idSyarat)->get() as $dokumenLama) {
                                $lama[] = $dokumenLama->lokasi_file;
                                $dokumenLama->delete();
                            }
                            $this->simpanBerkas($p, $file, (int) $idSyarat, $baru);
                        }

                        $this->alur->ubah(
                            $p,
                            StatusPermohonan::DalamPemeriksaan,
                            null,
                            $keterangan ? "Pemohon mengirim perbaikan. {$keterangan}" : 'Pemohon mengirim perbaikan.'
                        );

                        return $p;
                    });
                } catch (Throwable $e) {
                    $this->hapusBerkas($baru);
                    throw $e;
                }

                $this->hapusBerkas($lama);

                return $hasil->refresh();
            });
        } catch (LockTimeoutException) {
            throw new DomainException('Perbaikan sebelumnya masih diproses. Silakan coba lagi sebentar lagi.');
        }
    }

    /** Persyaratan aktif milik layanan, berurutan. */
    public function persyaratanAktif(JenisLayanan $layanan): Collection
    {
        return $layanan->persyaratan()->where('aktif', true)->get();
    }

    private function pastikanMasyarakat(User $user): void
    {
        if (! $user->isAktif() || ! $user->hasRole('masyarakat') || ! $user->masyarakat()->exists()) {
            throw new AuthorizationException('Hanya masyarakat yang dapat mengajukan atau memperbaiki permohonan.');
        }
    }

    private function dariToken(string $kunci, User $pemohon): ?Permohonan
    {
        $id = Cache::get($kunci);

        return $id ? Permohonan::where('id', $id)->where('user_id', $pemohon->id)->first() : null;
    }

    /** @return array<int|string, UploadedFile> */
    private function hanyaBerkas(array $berkas): array
    {
        return array_filter($berkas, fn ($f): bool => $f instanceof UploadedFile);
    }

    private function rapikanIsian(array $isian): array
    {
        return array_map(fn ($v) => is_string($v) ? trim($v) : $v, $isian);
    }

    /**
     * Validasi kelengkapan (FR-33, FR-34, FR-35). Melempar ValidationException dengan
     * kunci: alasan_pengajuan, isian.{id}, berkas.{id}, isian, berkas, layanan.
     */
    private function validasi(
        Collection $persyaratan,
        ?string $alasan,
        array $isianBaru,
        array $berkas,
        array $isianAda = [],
        array $dokumenAda = [],
        bool $denganAlasan = true,
    ): void {
        $idIsian = $persyaratan->filter(fn ($p): bool => $p->tipe_persyaratan === TipePersyaratan::Isian)->pluck('id')->all();
        $idDokumen = $persyaratan->filter(fn ($p): bool => $p->tipe_persyaratan === TipePersyaratan::Dokumen)->pluck('id')->all();

        // Persyaratan harus berasal dari layanan yang sama (SRS 6.5.2).
        $asing = [];
        if (array_diff(array_keys($isianBaru), $idIsian)) {
            $asing['isian'] = 'Data isian tidak sesuai dengan layanan yang dipilih.';
        }
        if (array_diff(array_keys($berkas), $idDokumen)) {
            $asing['berkas'] = 'Dokumen tidak sesuai dengan layanan yang dipilih.';
        }
        if ($asing) {
            throw ValidationException::withMessages($asing);
        }

        $aturan = [];
        $pesan = [];
        $atribut = [];

        if ($denganAlasan) {
            $aturan['alasan_pengajuan'] = ['required', 'string', 'min:5', 'max:1000'];
            $atribut['alasan_pengajuan'] = 'Alasan pengajuan';
        }

        foreach ($persyaratan as $p) {
            if ($p->tipe_persyaratan === TipePersyaratan::Isian) {
                $kunci = "isian.{$p->id}";
                $aturan[$kunci] = array_merge(
                    [$p->wajib ? 'required' : 'nullable'],
                    match ($p->tipe_input) {
                        'angka' => ['numeric'],
                        'tanggal' => ['date'],
                        'pilihan' => [Rule::in($p->opsi ?? [])],
                        'area_teks' => ['string', 'max:2000'],
                        default => ['string', 'max:255'],
                    }
                );
                $atribut[$kunci] = $p->nama_persyaratan;
            } else {
                $kunci = "berkas.{$p->id}";
                $perlu = $p->wajib && ! in_array($p->id, $dokumenAda, true);
                $aturan[$kunci] = [$perlu ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:' . self::MAKS_KB];
                $atribut[$kunci] = $p->nama_persyaratan;
                $pesan["{$kunci}.required"] = "Dokumen {$p->nama_persyaratan} wajib diunggah.";
            }
        }

        $isianGabungan = array_intersect_key(array_replace($isianAda, $isianBaru), array_flip($idIsian));

        Validator::make(
            ['alasan_pengajuan' => $alasan, 'isian' => $isianGabungan, 'berkas' => $berkas],
            $aturan,
            $pesan + self::PESAN_UMUM,
            $atribut
        )->validate();
    }

    /** Menyimpan permohonan baru. Bila nomor bentrok (pengajuan bersamaan), diulang otomatis. */
    private function simpanBaru(User $pemohon, JenisLayanan $layanan, ?string $alasan, Collection $persyaratan, array $isian, array $berkas): Permohonan
    {
        for ($coba = 1; ; $coba++) {
            $tersimpan = [];

            try {
                return DB::transaction(function () use ($pemohon, $layanan, $alasan, $isian, $berkas, &$tersimpan) {
                    $tahun = (int) now()->year;
                    $urut = (int) Permohonan::where('tahun', $tahun)->max('urut') + 1;

                    $permohonan = Permohonan::create([
                        'user_id' => $pemohon->id,
                        'jenis_layanan_id' => $layanan->id,
                        'nomor_permohonan' => sprintf('REG-%s-%d-%04d', $layanan->kode, $tahun, $urut),
                        'tahun' => $tahun,
                        'urut' => $urut,
                        'tanggal_pengajuan' => now(),
                        'alasan_pengajuan' => $alasan,
                        'status' => StatusPermohonan::Diajukan,
                    ]);

                    $this->simpanIsian($permohonan, $isian);

                    foreach ($berkas as $idSyarat => $file) {
                        $this->simpanBerkas($permohonan, $file, (int) $idSyarat, $tersimpan);
                    }

                    $this->alur->catatAwal($permohonan);

                    return $permohonan;
                });
            } catch (UniqueConstraintViolationException $e) {
                $this->hapusBerkas($tersimpan);

                if ($coba >= 5) {
                    throw $e;
                }
            } catch (Throwable $e) {
                $this->hapusBerkas($tersimpan);

                throw $e;
            }
        }
    }

    /** Menyimpan atau memperbarui isian. Nilai kosong menghapus isian (hanya lolos validasi bila tidak wajib). */
    private function simpanIsian(Permohonan $permohonan, array $isian): void
    {
        foreach ($isian as $idSyarat => $nilai) {
            $nilai = is_scalar($nilai) ? trim((string) $nilai) : '';

            if ($nilai === '') {
                IsianPermohonan::where('permohonan_id', $permohonan->id)->where('persyaratan_id', (int) $idSyarat)->delete();

                continue;
            }

            IsianPermohonan::updateOrCreate(
                ['permohonan_id' => $permohonan->id, 'persyaratan_id' => (int) $idSyarat],
                ['nilai_isian' => $nilai]
            );
        }
    }

    /** Berkas disimpan di disk privat dengan nama acak; nama asli hanya menjadi label. */
    private function simpanBerkas(Permohonan $permohonan, UploadedFile $file, int $idSyarat, array &$tersimpan): Dokumen
    {
        $mime = (string) $file->getMimeType();
        $ekstensi = self::MIME_EKSTENSI[$mime] ?? null;

        if (! $ekstensi) {
            throw ValidationException::withMessages(["berkas.{$idSyarat}" => 'Format berkas tidak didukung.']);
        }

        $path = Storage::disk('local')->putFileAs("dokumen/{$permohonan->id}", $file, Str::uuid() . '.' . $ekstensi);

        if ($path === false) {
            throw new DomainException('Berkas gagal disimpan. Silakan coba lagi.');
        }

        $tersimpan[] = $path;

        return Dokumen::create([
            'permohonan_id' => $permohonan->id,
            'persyaratan_id' => $idSyarat,
            'nama_file' => $this->namaAman($file->getClientOriginalName(), $ekstensi),
            'lokasi_file' => $path,
            'tipe_file' => $mime,
            'ukuran_file' => (int) $file->getSize(),
            'status_verifikasi' => StatusVerifikasiDokumen::BelumDiperiksa,
            'tanggal_upload' => now(),
        ]);
    }

    private function namaAman(string $asli, string $ekstensi): string
    {
        $dasar = pathinfo($asli, PATHINFO_FILENAME);
        $dasar = preg_replace('/[^\pL\pN\s._()-]+/u', '', $dasar) ?? '';
        $dasar = Str::limit(trim($dasar), 80, '');

        return ($dasar !== '' ? $dasar : 'berkas') . '.' . $ekstensi;
    }

    /** @param  array<int, string>  $path */
    private function hapusBerkas(array $path): void
    {
        foreach ($path as $p) {
            Storage::disk('local')->delete($p);
        }
    }
}
