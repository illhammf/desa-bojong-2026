<?php

use App\Models\JenisLayanan;
use App\Models\Persyaratan;
use App\Policies\KelolaLayananPolicy;
use App\Policies\LogAktivitasPolicy;
use App\Policies\PersyaratanPolicy;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

/*
 * Startup kontainer menjalankan shield:generate yang menimpa/membuat berkas kebijakan dan izin.
 * Tes ini menjaga agar kebijakan buatan kita tidak ikut tertimpa.
 */

it('mengecualikan resource yang aturannya terkunci di kode dari generator Shield', function () {
    expect(config('filament-shield.exclude.enabled'))->toBeTrue();
    expect(config('filament-shield.exclude.resources'))
        ->toContain('PermohonanResource')
        ->toContain('JenisLayananResource')
        ->toContain('ActivityResource');
});

it('tidak membuat izin halaman dan widget, aksesnya dijaga pemeriksaan role', function () {
    expect(config('filament-shield.entities.pages'))->toBeFalse();
    expect(config('filament-shield.entities.widgets'))->toBeFalse();
});

it('mendaftarkan kebijakan penting secara eksplisit', function () {
    expect(Gate::getPolicyFor(JenisLayanan::class))->toBeInstanceOf(KelolaLayananPolicy::class);
    expect(Gate::getPolicyFor(Persyaratan::class))->toBeInstanceOf(PersyaratanPolicy::class);
    expect(Gate::getPolicyFor(Activity::class))->toBeInstanceOf(LogAktivitasPolicy::class);
});

it('tidak ada berkas kebijakan hasil generate Shield untuk resource yang dikecualikan', function () {
    foreach (['PermohonanPolicy', 'JenisLayananPolicy', 'ActivityPolicy'] as $nama) {
        expect(file_exists(app_path("Policies/{$nama}.php")))->toBeFalse();
    }
});
