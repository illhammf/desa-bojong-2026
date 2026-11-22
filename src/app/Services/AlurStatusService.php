<?php

namespace App\Services;

use App\Enums\StatusPermohonan;
use App\Enums\StatusVerifikasiDokumen;
use App\Models\Permohonan;
use App\Models\RiwayatStatus;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu perubahan status permohonan (PRD 7.7, FR-41, FR-42).
 * $oleh = null berarti perubahan otomatis oleh sistem (pengajuan, pengiriman perbaikan).
 */
class AlurStatusService
{
    private const KETERANGAN_DEFAULT = [
        'diajukan' => 'Permohonan diterima sistem.',
        'dalam_pemeriksaan' => 'Petugas mulai memeriksa berkas.',
        'diproses' => 'Data dan dokumen dinyatakan sesuai.',
        'siap_diambil' => 'Surat fisik siap diambil di Balai Desa Bojong.',
    ];

    public function ubah(Permohonan $permohonan, StatusPermohonan $tujuan, ?User $oleh = null, ?string $keterangan = null): Permohonan
    {
        // BR-03, BR-05, BR-33: hanya Petugas yang boleh mengubah status secara manual.
        if ($oleh !== null && ! $oleh->hasRole('petugas')) {
            throw new AuthorizationException('Hanya Petugas Desa yang dapat memperbarui status permohonan.');
        }

        if ($oleh !== null && ! $oleh->petugas()->exists()) {
            throw new DomainException('Akun ini belum memiliki data profil petugas.');
        }

        DB::transaction(function () use ($permohonan, $tujuan, $oleh, $keterangan) {
            $p = Permohonan::query()->lockForUpdate()->findOrFail($permohonan->id);
            $asal = $p->status;

            if (! $asal->bolehKe($tujuan)) {
                throw new DomainException("Status tidak dapat diubah dari {$asal->getLabel()} ke {$tujuan->getLabel()}.");
            }

            if ($oleh !== null && $asal === StatusPermohonan::PerluPerbaikan) {
                throw new DomainException('Permohonan sedang menunggu perbaikan dari pemohon.');
            }

            if ($tujuan === StatusPermohonan::PerluPerbaikan && blank($keterangan)) {
                throw new DomainException('Catatan perbaikan wajib diisi.');
            }

            if ($tujuan === StatusPermohonan::Diproses
                && $p->dokumen()->where('status_verifikasi', '!=', StatusVerifikasiDokumen::Sesuai->value)->exists()) {
                throw new DomainException('Seluruh dokumen harus ditandai Sesuai sebelum permohonan diproses.');
            }

            $p->status = $tujuan;
            if ($tujuan === StatusPermohonan::PerluPerbaikan) {
                $p->catatan_petugas = $keterangan;
            }
            $p->save();

            RiwayatStatus::create([
                'permohonan_id' => $p->id,
                'petugas_id' => $oleh?->id,
                'status' => $tujuan,
                'keterangan' => $keterangan ?: (self::KETERANGAN_DEFAULT[$tujuan->value] ?? null),
                'waktu_perubahan' => now(),
            ]);
        });

        return $permohonan->refresh();
    }
}
