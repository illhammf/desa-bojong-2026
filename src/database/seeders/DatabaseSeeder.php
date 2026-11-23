<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Activitylog\ActivityLogStatus;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Data awal bukan tindakan pengguna, jadi tidak dicatat pada log aktivitas (FR-51).
        $status = app(ActivityLogStatus::class);
        $status->disable();

        try {
            $this->call([
                RoleSeeder::class,
                HakAksesSeeder::class,
                UserSeeder::class,
                JenisLayananSeeder::class,
                PersyaratanSeeder::class,
                PengaturanSitusSeeder::class,
            ]);

            // Data contoh hanya untuk pengembangan lokal
            if (app()->environment('local')) {
                $this->call(PermohonanSeeder::class);
            }
        } finally {
            $status->enable();
        }
    }
}
