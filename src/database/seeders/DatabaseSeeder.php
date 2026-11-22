<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
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
    }
}
