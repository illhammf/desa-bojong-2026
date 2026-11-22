<?php

namespace App\Filament\Admin\Pages;

use App\Enums\StatusPermohonan;
use App\Filament\Admin\Resources\PermohonanResource;
use App\Models\JenisLayanan;
use App\Models\Permohonan;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LaporanPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Pelayanan';

    protected static ?string $navigationLabel = 'Laporan';

    protected static ?string $title = 'Laporan Pelayanan Administrasi';

    protected static ?string $slug = 'laporan';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.admin.pages.laporan';

    /** FR-26 (petugas), FR-31 (admin), FR-44. Sementara berbasis role, nanti dialihkan ke permission. */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['petugas', 'admin', 'super_admin']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Permohonan::query()->with(['pemohon', 'layanan']))
            ->heading('Rincian Permohonan')
            ->defaultSort('tanggal_pengajuan', 'desc')
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->emptyStateHeading('Tidak ada permohonan')
            ->emptyStateDescription('Tidak ada permohonan yang sesuai dengan filter.')
            ->columns([
                Tables\Columns\TextColumn::make('nomor_permohonan')
                    ->label('Nomor Permohonan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('pemohon.name')
                    ->label('Pemohon')
                    ->searchable(),
                Tables\Columns\TextColumn::make('layanan.nama_layanan')
                    ->label('Jenis Layanan')
                    ->wrap(),
                Tables\Columns\TextColumn::make('tanggal_pengajuan')
                    ->label('Tanggal Pengajuan')
                    ->dateTime('d M Y, H.i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('tanggal')
                    ->label('Periode')
                    ->form([
                        Forms\Components\DatePicker::make('dari')->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('sampai')->label('Sampai Tanggal'),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, $d) => $q->whereDate('tanggal_pengajuan', '>=', $d))
                        ->when($data['sampai'] ?? null, fn (Builder $q, $d) => $q->whereDate('tanggal_pengajuan', '<=', $d))),
                Tables\Filters\SelectFilter::make('jenis_layanan_id')
                    ->label('Jenis Layanan')
                    ->relationship('layanan', 'nama_layanan')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusPermohonan::class),
            ])
            ->actions([
                Tables\Actions\Action::make('periksa')
                    ->label('Periksa')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->url(fn (Permohonan $record): string => PermohonanResource::getUrl('view', ['record' => $record]))
                    ->visible(fn (): bool => PermohonanResource::bolehKelola()),
            ]);
    }

    public function keteranganPeriode(): string
    {
        $f = $this->tableFilters['tanggal'] ?? [];
        $dari = $f['dari'] ?? null;
        $sampai = $f['sampai'] ?? null;

        if (! $dari && ! $sampai) {
            return 'Semua periode';
        }

        $fmt = fn (string $t): string => Carbon::parse($t)->translatedFormat('d F Y');

        return match (true) {
            (bool) ($dari && $sampai) => $fmt($dari) . ' - ' . $fmt($sampai),
            (bool) $dari => 'Sejak ' . $fmt($dari),
            default => 'Sampai ' . $fmt($sampai),
        };
    }

    /**
     * Ringkasan per status dan rekap per layanan, dihitung dari query yang sama dengan tabel
     * (sudah terkena filter), sehingga angka selalu sejalan dengan rincian.
     *
     * @return array{total: int, statuses: array<string, array{label: string, jumlah: int}>, baris: array<int, array<string, mixed>>}
     */
    public function ringkasan(): array
    {
        $data = $this->getFilteredTableQuery()
            ->setEagerLoads([])
            ->reorder()
            ->select('permohonan.jenis_layanan_id', 'permohonan.status')
            ->selectRaw('count(*) as total')
            ->groupBy('permohonan.jenis_layanan_id', 'permohonan.status')
            ->get();

        $nolStatus = [];
        foreach (StatusPermohonan::cases() as $s) {
            $nolStatus[$s->value] = 0;
        }

        $perStatus = $nolStatus;
        $matriks = [];

        foreach ($data as $b) {
            $kode = $b->status->value;
            $n = (int) $b->total;
            $id = $b->jenis_layanan_id;

            $perStatus[$kode] += $n;
            $matriks[$id] ??= ['per' => $nolStatus, 'total' => 0];
            $matriks[$id]['per'][$kode] += $n;
            $matriks[$id]['total'] += $n;
        }

        $layanan = JenisLayanan::whereIn('id', array_keys($matriks))->get()->keyBy('id');

        $baris = [];
        foreach ($matriks as $id => $m) {
            $baris[] = [
                'kode' => $layanan[$id]->kode ?? '-',
                'nama' => $layanan[$id]->nama_layanan ?? '-',
                'per' => $m['per'],
                'total' => $m['total'],
            ];
        }
        usort($baris, fn (array $a, array $b): int => [$b['total'], $a['nama']] <=> [$a['total'], $b['nama']]);

        $statuses = [];
        foreach (StatusPermohonan::cases() as $s) {
            $statuses[$s->value] = ['label' => $s->getLabel(), 'jumlah' => $perStatus[$s->value]];
        }

        return [
            'total' => array_sum($perStatus),
            'statuses' => $statuses,
            'baris' => $baris,
        ];
    }
}
