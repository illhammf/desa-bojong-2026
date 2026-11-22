<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_situs', function (Blueprint $table) {
            $table->id();
            // identitas situs
            $table->string('nama_situs')->nullable();
            $table->string('tagline')->nullable();
            $table->string('logo')->nullable();
            $table->string('foto_beranda')->nullable();
            // instansi dan kontak
            $table->string('nama_instansi')->nullable();
            $table->text('alamat_kantor')->nullable();
            $table->string('kode_pos', 10)->nullable();
            $table->string('telepon', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('jam_layanan')->nullable();
            // konten beranda
            $table->string('judul_hero')->nullable();
            $table->text('deskripsi_hero')->nullable();
            $table->text('pengumuman')->nullable();
            $table->string('judul_informasi')->nullable();
            $table->text('isi_informasi')->nullable();
            $table->json('langkah_alur')->nullable();
            $table->text('catatan_pengambilan')->nullable();
            $table->string('teks_footer')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_situs');
    }
};
