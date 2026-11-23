<?php

use App\Models\User;
use App\Policies\AkunPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

it('kebijakan akun yang aktif adalah AkunPolicy, bukan berkas hasil generate Shield', function () {
    expect(Gate::getPolicyFor(User::class))->toBeInstanceOf(AkunPolicy::class);
});
