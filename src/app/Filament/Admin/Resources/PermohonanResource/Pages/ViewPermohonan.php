<?php

namespace App\Filament\Admin\Resources\PermohonanResource\Pages;

use App\Enums\StatusPermohonan;
use App\Filament\Admin\Resources\PermohonanResource;
use App\Services\AlurStatusService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Auth\Access\AuthorizationException;

class ViewPermohonan extends ViewRecord
{
    protected static string $resource = PermohonanResource::class;

    public function getTitle(): string
    {
        return 'Detail dan Verifikasi Permohonan';
    }

    public function getSubheading(): ?string
    {
        return $this->record->nomor_permohonan;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->aksiStatus(
                'mulaiPemeriksaan', 'Mulai Pemeriksaan', 'heroicon-o-magnifying-glass', 'info',
                StatusPermohonan::Diajukan, StatusPermohonan::DalamPemeriksaan,
                'Mulai memeriksa permohonan ini?',
                'Status akan menjadi Dalam Pemeriksaan dan terlihat oleh pemohon.'
            ),

            Action::make('mintaPerbaikan')
                ->label('Minta Perbaikan')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->visible(fn (): bool => $this->bisaAksi(StatusPermohonan::DalamPemeriksaan))
                ->modalHeading('Minta perbaikan permohonan')
                ->modalSubmitActionLabel('Kirim Permintaan')
                ->form([
                    Forms\Components\Textarea::make('catatan')
                        ->label('Catatan Perbaikan untuk Pemohon')
                        ->required()
                        ->minLength(10)
                        ->maxLength(500)
                        ->rows(4)
                        ->helperText('Jelaskan bagian yang harus diperbaiki. Catatan ini tampil pada akun pemohon.'),
                ])
                ->action(fn (array $data) => $this->ubahStatus(StatusPermohonan::PerluPerbaikan, $data['catatan'])),

            $this->aksiStatus(
                'proses', 'Proses Permohonan', 'heroicon-o-check-circle', 'primary',
                StatusPermohonan::DalamPemeriksaan, StatusPermohonan::Diproses,
                'Nyatakan data dan dokumen sesuai?',
                'Pastikan data dan seluruh dokumen sudah diperiksa. Status akan menjadi Diproses.'
            ),

            $this->aksiStatus(
                'siapDiambil', 'Tandai Siap Diambil', 'heroicon-o-archive-box-arrow-down', 'success',
                StatusPermohonan::Diproses, StatusPermohonan::SiapDiambil,
                'Surat fisik sudah siap diambil?',
                'Pastikan surat sudah dicetak, ditandatangani, dan distempel. Pemohon akan melihat informasi pengambilan.'
            ),
        ];
    }

    private function bisaAksi(StatusPermohonan $dari): bool
    {
        return PermohonanResource::bolehKelola() && $this->record->status === $dari;
    }

    private function aksiStatus(
        string $nama,
        string $label,
        string $ikon,
        string $warna,
        StatusPermohonan $dari,
        StatusPermohonan $ke,
        string $judul,
        string $deskripsi,
    ): Action {
        return Action::make($nama)
            ->label($label)
            ->icon($ikon)
            ->color($warna)
            ->visible(fn (): bool => $this->bisaAksi($dari))
            ->requiresConfirmation()
            ->modalHeading($judul)
            ->modalDescription($deskripsi)
            ->action(fn () => $this->ubahStatus($ke));
    }

    private function ubahStatus(StatusPermohonan $ke, ?string $catatan = null): void
    {
        try {
            app(AlurStatusService::class)->ubah($this->record, $ke, auth()->user(), $catatan);
        } catch (DomainException|AuthorizationException $e) {
            Notification::make()
                ->danger()
                ->title('Status tidak dapat diubah')
                ->body($e->getMessage())
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Status diperbarui: ' . $ke->getLabel())
            ->send();

        $this->redirect(PermohonanResource::getUrl('view', ['record' => $this->record]));
    }
}
