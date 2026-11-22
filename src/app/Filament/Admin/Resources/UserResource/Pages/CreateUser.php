<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Services\PenggunaService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function beforeCreate(): void
    {
        $error = PenggunaService::periksa(auth()->user(), null, $this->data);

        if ($error) {
            Notification::make()->danger()->title('Akun tidak dapat dibuat')->body($error)->send();
            $this->halt();
        }
    }

    protected function afterCreate(): void
    {
        app(PenggunaService::class)->terapkan($this->record, $this->data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
