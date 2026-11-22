<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permohonan_id')->constrained('permohonan')->cascadeOnDelete();
            $table->foreignId('persyaratan_id')->nullable()->constrained('persyaratan')->restrictOnDelete();
            $table->string('nama_file', 255);
            $table->string('lokasi_file', 255);
            $table->string('tipe_file', 50);
            $table->unsignedInteger('ukuran_file');            // dalam byte
            $table->string('status_verifikasi', 20)->default('belum_diperiksa');
            $table->dateTime('tanggal_upload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen');
    }
};
