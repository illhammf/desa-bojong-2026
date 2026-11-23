<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

it('tidak menghapus empat peran inti', function (string $nama) {
    expect(fn () => Role::findByName($nama)->delete())->toThrow(ValidationException::class);
    expect(Role::where('name', $nama)->exists())->toBeTrue();
})->with(['masyarakat', 'petugas', 'admin', 'super_admin']);

it('tidak mengganti nama empat peran inti', function (string $nama) {
    $role = Role::findByName($nama);
    $role->name = 'nama_lain';

    expect(fn () => $role->save())->toThrow(ValidationException::class);
    expect(Role::where('name', $nama)->exists())->toBeTrue();
})->with(['masyarakat', 'petugas', 'admin', 'super_admin']);

it('tetap mengizinkan peran non-inti diubah dan dihapus, serta peran inti diubah selain namanya', function () {
    $role = Role::create(['name' => 'uji_peran', 'guard_name' => 'web']);
    $role->name = 'uji_peran2';
    $role->save();
    $role->delete();
    expect(Role::where('name', 'like', 'uji_peran%')->exists())->toBeFalse();

    $inti = Role::findByName('admin');
    $inti->givePermissionTo('view_any_user');
    expect(Role::findByName('admin')->name)->toBe('admin');
});
