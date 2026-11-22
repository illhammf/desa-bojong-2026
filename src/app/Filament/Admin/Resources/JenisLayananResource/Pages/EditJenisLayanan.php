<?php

namespace App\Filament\Admin\Resources\JenisLayananResource\Pages;

use App\Filament\Admin\Resources\JenisLayananResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJenisLayanan extends EditRecord
{
    protected static string $resource = JenisLayananResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
