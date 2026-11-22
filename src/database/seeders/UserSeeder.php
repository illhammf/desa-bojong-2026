<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Masyarakat;
use App\Models\Petugas;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Akun contoh untuk pengembangan (data mengikuti desain Figma).
     * Kata sandi 'password' HANYA untuk lingkungan lokal, ganti sebelum dipakai sungguhan.
     */
    public function run(): void
    {
        // Super Admin: hanya baris users + role, tanpa tabel profil
        $superAdmin = $this->buatUser('superadmin@demo.test', 'Super Admin', '081200000000');
        $superAdmin->syncRoles(['super_admin']);

        // Administrator
        $admin = $this->buatUser('admin@demo.test', 'Bambang Irawan, S.Kom', '081192381002');
        $admin->syncRoles(['admin']);
        Admin::updateOrCreate(['user_id' => $admin->id], ['nip' => '198402102009021002']);

        // Petugas Desa
        $petugas = $this->buatUser('petugas@demo.test', 'Ahmad Fauzi, S.AP', '081289441200');
        $petugas->syncRoles(['petugas']);
        Petugas::updateOrCreate(['user_id' => $petugas->id], ['nip' => '198904122015031004']);

        // Masyarakat
        $warga = $this->buatUser('warga@demo.test', 'Ahmad Subagja', '081200000001');
        $warga->syncRoles(['masyarakat']);
        Masyarakat::updateOrCreate(
            ['user_id' => $warga->id],
            [
                'nik' => '3603192008850003',
                'alamat' => 'Kp. Bojong Jaya, RT 003/RW 002, Desa Bojong, Kec. Cikupa, Kab. Tangerang',
            ]
        );
    }

    private function buatUser(string $email, string $nama, string $noHp): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $nama,
                'no_hp' => $noHp,
                'status_akun' => 'aktif',
                'password' => Hash::make('password'),
            ]
        );
    }
}
