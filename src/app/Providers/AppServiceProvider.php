<?php

namespace App\Providers;

use App\Models\JenisLayanan;
use App\Models\Persyaratan;
use App\Models\User;
use App\Policies\AkunPolicy;
use App\Policies\KelolaLayananPolicy;
use App\Policies\PersyaratanPolicy;
use App\Policies\LogAktivitasPolicy;
use App\Services\PenggunaService;
use Filament\Actions\MountableAction;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Activity::class, LogAktivitasPolicy::class);
        // Eksplisit agar tidak tertimpa berkas UserPolicy hasil generate Shield.
        Gate::policy(User::class, AkunPolicy::class);
        Gate::policy(JenisLayanan::class, KelolaLayananPolicy::class);
        Gate::policy(Persyaratan::class, PersyaratanPolicy::class);

        // Empat peran inti dipakai langsung oleh kode (canAccessPanel, AlurStatusService, PenggunaService).
        // Menghapus atau mengganti namanya akan merusak sistem, jadi dilarang dari mana pun.
        Role::deleting(function (Role $role) {
            if (in_array($role->name, array_keys(PenggunaService::LABEL), true)) {
                throw ValidationException::withMessages([
                    'name' => "Peran {$role->name} adalah peran inti sistem dan tidak dapat dihapus.",
                ]);
            }
        });

        Role::updating(function (Role $role) {
            $asli = (string) $role->getOriginal('name');

            if ($role->isDirty('name') && in_array($asli, array_keys(PenggunaService::LABEL), true)) {
                throw ValidationException::withMessages([
                    'name' => "Nama peran {$asli} adalah peran inti sistem dan tidak dapat diubah.",
                ]);
            }
        });
        Page::formActionsAlignment(Alignment::Right);
        Notifications::alignment(Alignment::End);
        Notifications::verticalAlignment(VerticalAlignment::End);
        Page::$reportValidationErrorUsing = function (ValidationException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();
        };
        MountableAction::configureUsing(function (MountableAction $action) {
            $action->modalFooterActionsAlignment(Alignment::Right);
        });
    }
}
