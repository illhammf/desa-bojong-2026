<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use App\Services\PenggunaService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->hidden(fn (User $record): bool => $record->id === auth()->id())
                ->before(fn ($action, User $record) => UserResource::cegahHapus($action, $record)),
        ];
    }

    protected function beforeSave(): void
    {
        $error = PenggunaService::periksa(auth()->user(), $this->record, $this->data);

        if ($error) {
            Notification::make()->danger()->title('Perubahan tidak dapat disimpan')->body($error)->send();
            $this->halt();
        }
    }

    protected function afterSave(): void
    {
        app(PenggunaService::class)->terapkan($this->record, $this->data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
