<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isian_permohonan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permohonan_id')->constrained('permohonan')->cascadeOnDelete();
            $table->foreignId('persyaratan_id')->constrained('persyaratan')->restrictOnDelete();
            $table->text('nilai_isian')->nullable();
            $table->timestamps();

            $table->unique(['permohonan_id', 'persyaratan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isian_permohonan');
    }
};
