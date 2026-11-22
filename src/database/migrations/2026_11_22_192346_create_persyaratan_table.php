<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persyaratan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_layanan_id')->constrained('jenis_layanan')->cascadeOnDelete();
            $table->string('nama_persyaratan', 150);
            $table->string('tipe_persyaratan', 20);            // dokumen | isian
            $table->string('tipe_input', 20)->default('teks'); // teks | area_teks | angka | tanggal | pilihan
            $table->json('opsi')->nullable();                  // daftar pilihan untuk tipe_input = pilihan
            $table->boolean('wajib')->default(true);
            $table->text('keterangan')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['jenis_layanan_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persyaratan');
    }
};
