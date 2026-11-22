<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permohonan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('masyarakat', 'user_id')->restrictOnDelete();
            $table->foreignId('jenis_layanan_id')->constrained('jenis_layanan')->restrictOnDelete();
            $table->string('nomor_permohonan', 50)->unique();  // REG-KODE-TAHUN-URUT
            $table->unsignedSmallInteger('tahun');
            $table->unsignedInteger('urut');
            $table->dateTime('tanggal_pengajuan');
            $table->text('alasan_pengajuan')->nullable();
            $table->string('status', 20)->default('diajukan')->index();
            $table->text('catatan_petugas')->nullable();
            $table->timestamps();

            $table->unique(['tahun', 'urut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permohonan');
    }
};
