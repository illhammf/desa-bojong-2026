<?php

namespace App\Services;

use App\Models\Masyarakat;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Registrasi akun masyarakat (BRD FR-01, PRD 7.1).
 * Peran selalu "masyarakat": tidak ada parameter peran, kolom tambahan dari luar diabaikan.
 * Tidak melakukan login otomatis.
 */
class RegistrasiService
{
    private const PESAN = [
        'required' => ':attribute wajib diisi.',
        'string' => ':attribute harus berupa teks.',
        'min.string' => ':attribute minimal :min karakter.',
        'max.string' => ':attribute maksimal :max karakter.',
        'email' => 'Format :attribute tidak valid.',
        'confirmed' => 'Konfirmasi kata sandi tidak sama.',
        'accepted' => ':attribute harus disetujui.',
        'nik.digits' => 'NIK harus berupa 16 digit angka.',
        'nik.unique' => 'NIK sudah terdaftar pada akun lain.',
        'email.unique' => 'Email sudah digunakan.',
        'no_hp.regex' => 'Nomor WhatsApp tidak valid. Gunakan nomor seluler Indonesia, misalnya 081234567890.',
        'password.min' => 'Kata sandi minimal 8 karakter.',
    ];

    private const ATRIBUT = [
        'nama' => 'Nama lengkap',
        'nik' => 'NIK',
        'alamat' => 'Alamat',
        'email' => 'Email',
        'no_hp' => 'Nomor WhatsApp',
        'password' => 'Kata sandi',
        'pernyataan' => 'Pernyataan kebenaran data',
    ];

    /**
     * @param  array<string, mixed>  $input  nama, nik, alamat, email, no_hp, password, password_confirmation, pernyataan
     *
     * @throws ValidationException
     */
    public function daftar(array $input): User
    {
        $data = $this->rapikan($input);

        Validator::make($data, [
            'nama' => ['required', 'string', 'min:2', 'max:255'],
            'nik' => ['required', 'digits:16', Rule::unique('masyarakat', 'nik')],
            'alamat' => ['required', 'string', 'min:5', 'max:500'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'no_hp' => ['required', 'regex:/^(\+62|62|0)8[0-9]{7,11}$/'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
            'pernyataan' => ['accepted'],
        ], self::PESAN, self::ATRIBUT)->validate();

        try {
            return DB::transaction(function () use ($data) {
                $user = User::create([
                    'name' => $data['nama'],
                    'email' => $data['email'],
                    'no_hp' => $data['no_hp'],
                    'status_akun' => 'aktif',
                    'password' => $data['password'],
                ]);

                $user->assignRole('masyarakat');

                Masyarakat::create([
                    'user_id' => $user->id,
                    'nik' => $data['nik'],
                    'alamat' => $data['alamat'],
                ]);

                return $user;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Pendaftaran bersamaan dengan NIK atau email yang sama: indeks unik basis data yang menentukan.
            $nik = str_contains(strtolower($e->getMessage()), 'nik');

            throw ValidationException::withMessages([
                $nik ? 'nik' : 'email' => $nik ? 'NIK sudah terdaftar pada akun lain.' : 'Email sudah digunakan.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function rapikan(array $input): array
    {
        $teks = fn (string $kunci): string => is_scalar($input[$kunci] ?? null) ? (string) $input[$kunci] : '';

        return [
            'nama' => trim($teks('nama')),
            'nik' => preg_replace('/\s+/', '', $teks('nik')),
            'alamat' => trim($teks('alamat')),
            'email' => Str::lower(trim($teks('email'))),
            'no_hp' => preg_replace('/[\s\-]+/', '', $teks('no_hp')),
            'password' => $teks('password'),
            'password_confirmation' => $teks('password_confirmation'),
            'pernyataan' => $input['pernyataan'] ?? null,
        ];
    }
}
