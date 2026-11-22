<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permohonan_id')->constrained('permohonan')->cascadeOnDelete();
            $table->foreignId('petugas_id')->nullable()->constrained('petugas', 'user_id')->nullOnDelete();
            $table->string('status', 20);
            $table->text('keterangan')->nullable();
            $table->dateTime('waktu_perubahan');

            $table->index(['permohonan_id', 'waktu_perubahan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_status');
    }
};
