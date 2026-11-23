<?php

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Illuminate\Support\Facades\Response;

/* NOTE: Do Not Remove
/ Livewire asset handling if using sub folder in domain
*/

Livewire::setUpdateRoute(function ($handle) {
    return Route::post(config('app.asset_prefix') . '/livewire/update', $handle);
});

Livewire::setScriptRoute(function ($handle) {
    return Route::get(config('app.asset_prefix') . '/livewire/livewire.js', $handle);
});
/*
/ END
*/
Route::get('/', function () {
    return view('welcome');
});

// Akses dokumen privat: pemilik permohonan atau petugas (PRD 7.16)
\Illuminate\Support\Facades\Route::get('/dokumen/{dokumen}', [\App\Http\Controllers\DokumenController::class, 'tampil'])
    ->middleware(['auth', \App\Http\Middleware\PastikanAkunAktif::class])
    ->name('dokumen.tampil');

// Sementara: pengunjung yang belum login diarahkan ke login panel (nanti diganti login Blade)
\Illuminate\Support\Facades\Route::redirect('/login', '/admin/login')->name('login');
