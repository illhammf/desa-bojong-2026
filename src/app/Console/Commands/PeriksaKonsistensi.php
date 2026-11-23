<?php

namespace App\Console\Commands;

use App\Models\Permohonan;
use Illuminate\Console\Command;

class PeriksaKonsistensi extends Command
{
    protected $signature = 'app:periksa-konsistensi';

    protected $description = 'Periksa status permohonan sama dengan riwayat terakhir dan nomor pengajuan tidak ganda (NFR-27 s.d. NFR-29)';

    public function handle(): int
    {
        $masalah = [];
        $total = 0;

        Permohonan::query()->orderBy('id')->chunkById(200, function ($kelompok) use (&$masalah, &$total) {
            $kelompok->load('riwayat');

            foreach ($kelompok as $p) {
                $total++;
                $akhir = $p->riwayat->last();

                if (! $akhir) {
                    $masalah[] = "{$p->nomor_permohonan}: tidak memiliki riwayat status";
                } elseif ($akhir->status !== $p->status) {
                    $masalah[] = "{$p->nomor_permohonan}: status {$p->status->value} tidak sama dengan riwayat terakhir {$akhir->status->value}";
                }
            }
        });

        $ganda = Permohonan::query()->select('nomor_permohonan')->groupBy('nomor_permohonan')->havingRaw('count(*) > 1')->pluck('nomor_permohonan');
        foreach ($ganda as $nomor) {
            $masalah[] = "{$nomor}: nomor pengajuan ganda";
        }

        if (! $masalah) {
            $this->info("Konsisten: {$total} permohonan diperiksa, tidak ada masalah.");

            return self::SUCCESS;
        }

        foreach ($masalah as $baris) {
            $this->error($baris);
        }
        $this->error(count($masalah) . " masalah ditemukan pada {$total} permohonan.");

        return self::FAILURE;
    }
}
