<?php

use App\Filament\Admin\Resources\UserResource\Pages\CreateUser;
use App\Filament\Admin\Resources\UserResource\Pages\EditUser;
use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Models\Admin;
use App\Models\Masyarakat;
use App\Models\Petugas;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Skenario;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Storage::fake('local');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->admin = Skenario::user('admin');
    $this->sa = Skenario::user('superadmin');
    $this->petugas = Skenario::user('petugas');
    $this->warga = Skenario::user('warga');
});

function isianAkunBaru(array $tambahan = []): array
{
    return array_merge([
        'name' => 'Akun Baru Uji',
        'email' => 'akun.baru@uji.test',
        'no_hp' => '081234567891',
        'status_akun' => 'aktif',
        'password' => 'kata-sandi-123',
        'password_confirmation' => 'kata-sandi-123',
    ], $tambahan);
}

it('administrator tidak melihat Administrator lain maupun Super Admin pada daftar akun (BR-30)', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$this->admin, $this->petugas, $this->warga])
        ->assertCanNotSeeTableRecords([$this->sa]);
});

it('Super Admin melihat seluruh akun', function () {
    $this->actingAs($this->sa);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$this->sa, $this->admin, $this->petugas, $this->warga]);
});

it('daftar akun tertutup bagi petugas dan masyarakat', function (string $peran) {
    $this->actingAs(Skenario::user($peran));

    Livewire::test(ListUsers::class)->assertForbidden();
})->with(['petugas', 'warga']);

it('administrator membuat akun petugas lengkap dengan NIP (FR-27, FR-28)', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateUser::class)
        ->fillForm(isianAkunBaru(['role' => 'petugas', 'nip' => '199001012020011001']))
        ->call('create')
        ->assertHasNoFormErrors();

    $akun = User::where('email', 'akun.baru@uji.test')->first();

    expect($akun)->not->toBeNull();
    expect($akun->getRoleNames()->all())->toEqual(['petugas']);
    expect($akun->status_akun)->toBe('aktif');
    expect(Petugas::find($akun->id)?->nip)->toBe('199001012020011001');
});

it('administrator membuat akun masyarakat lengkap dengan NIK dan alamat', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateUser::class)
        ->fillForm(isianAkunBaru(['role' => 'masyarakat', 'nik' => '3603190101900002', 'alamat' => 'Kp. Bojong Uji RT 001/RW 001']))
        ->call('create')
        ->assertHasNoFormErrors();

    $akun = User::where('email', 'akun.baru@uji.test')->first();

    expect($akun->getRoleNames()->all())->toEqual(['masyarakat']);
    expect(Masyarakat::find($akun->id)->nik)->toBe('3603190101900002');
});

it('akun masyarakat menolak NIK yang tidak sah atau sudah terdaftar', function (string $nik) {
    $this->actingAs($this->admin);

    Livewire::test(CreateUser::class)
        ->fillForm(isianAkunBaru(['role' => 'masyarakat', 'nik' => $nik, 'alamat' => 'Alamat uji']))
        ->call('create')
        ->assertHasFormErrors(['nik']);

    expect(User::where('email', 'akun.baru@uji.test')->exists())->toBeFalse();
})->with([
    'terlalu pendek' => '123',
    'berisi huruf' => '36031901019000ab',
    'sudah terdaftar' => '3603192008850003',
]);

it('administrator tidak dapat membuat akun Administrator atau Super Admin (BR-30)', function (string $peran) {
    $this->actingAs($this->admin);

    Livewire::test(CreateUser::class)
        ->fillForm(isianAkunBaru(['role' => $peran, 'nip' => '1234']))
        ->call('create');

    expect(User::where('email', 'akun.baru@uji.test')->exists())->toBeFalse();
})->with(['admin', 'super_admin']);

it('Super Admin membuat akun Administrator (FR-46)', function () {
    $this->actingAs($this->sa);

    Livewire::test(CreateUser::class)
        ->fillForm(isianAkunBaru(['role' => 'admin', 'nip' => '198001012010011001']))
        ->call('create')
        ->assertHasNoFormErrors();

    $akun = User::where('email', 'akun.baru@uji.test')->first();

    expect($akun->getRoleNames()->all())->toEqual(['admin']);
    expect(Admin::find($akun->id)?->nip)->toBe('198001012010011001');
});

it('administrator mengubah data akun petugas tanpa mengubah perannya', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditUser::class, ['record' => $this->petugas->getRouteKey()])
        ->fillForm(['name' => 'Petugas Nama Baru'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->petugas->refresh()->name)->toBe('Petugas Nama Baru');
    expect($this->petugas->getRoleNames()->all())->toEqual(['petugas']);
});

it('peran dan status akun sendiri terkunci pada formulir ubah (BR-31)', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditUser::class, ['record' => $this->admin->getRouteKey()])
        ->assertFormFieldIsDisabled('role')
        ->assertFormFieldIsDisabled('status_akun');
});

it('akun dinonaktifkan dan diaktifkan kembali lewat tombol, kecuali akun sendiri', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListUsers::class)->callTableAction('toggleStatus', $this->petugas);
    expect($this->petugas->refresh()->status_akun)->toBe('nonaktif');

    Livewire::test(ListUsers::class)->callTableAction('toggleStatus', $this->petugas);
    expect($this->petugas->refresh()->status_akun)->toBe('aktif');

    Livewire::test(ListUsers::class)->assertTableActionHidden('toggleStatus', $this->admin);
});

it('Super Admin tidak dapat menonaktifkan atau menghapus dirinya sendiri (BR-31, BR-32)', function () {
    $this->actingAs($this->sa);

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('toggleStatus', $this->sa)
        ->assertTableActionHidden('delete', $this->sa);
});

it('akun yang sudah memiliki permohonan tidak dapat dihapus, akun bersih dapat', function () {
    $this->actingAs($this->admin);
    Skenario::ajukanSiu($this->warga);
    $bersih = Skenario::buatUser('petugas', 'bersih@uji.test');

    Livewire::test(ListUsers::class)->callTableAction(DeleteAction::class, $this->warga);
    expect(User::where('email', 'warga@demo.test')->exists())->toBeTrue();

    Livewire::test(ListUsers::class)->callTableAction(DeleteAction::class, $bersih);
    expect(User::where('email', 'bersih@uji.test')->exists())->toBeFalse();
});
