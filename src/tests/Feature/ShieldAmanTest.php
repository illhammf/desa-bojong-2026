<?php

use App\Models\JenisLayanan;
use App\Models\Persyaratan;
use App\Policies\KelolaLayananPolicy;
use App\Policies\PersyaratanPolicy;
use Illuminate\Support\Facades\Gate;

/*
 * Startup kontainer menjalankan shield:generate yang menimpa/membuat berkas kebijakan.
 * Tes ini menjaga agar kebijakan buatan kita tidak ikut tertimpa.
 */

it('mengecualikan resource yang aturannya terkunci di kode dari generator Shield', function () {
    expect(config('filament-shield.exclude.enabled'))->toBeTrue();
    expect(config('filament-shield.exclude.resources'))
        ->toContain('PermohonanResource')
        ->toContain('JenisLayananResource');
});

it('mendaftarkan kebijakan master data secara eksplisit', function () {
    expect(Gate::getPolicyFor(JenisLayanan::class))->toBeInstanceOf(KelolaLayananPolicy::class);
    expect(Gate::getPolicyFor(Persyaratan::class))->toBeInstanceOf(PersyaratanPolicy::class);
});

it('tidak ada berkas kebijakan hasil generate Shield untuk resource yang dikecualikan', function () {
    expect(file_exists(app_path('Policies/PermohonanPolicy.php')))->toBeFalse();
    expect(file_exists(app_path('Policies/JenisLayananPolicy.php')))->toBeFalse();
});
