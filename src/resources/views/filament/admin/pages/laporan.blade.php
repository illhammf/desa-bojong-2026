<x-filament-panels::page>
    @php
        $r = $this->ringkasan();
        $sel = 'padding:.5rem .75rem;border:1px solid rgba(128,128,128,.25);';
    @endphp

    <div style="font-size:.875rem;opacity:.8;">
        Periode: <strong>{{ $this->keteranganPeriode() }}</strong>
        <span style="opacity:.7;"> (atur filter pada tabel di bawah)</span>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;">
        <x-filament::section>
            <div style="font-size:.8rem;opacity:.7;">Total Permohonan</div>
            <div style="font-size:1.75rem;font-weight:700;line-height:1.2;">{{ $r['total'] }}</div>
        </x-filament::section>

        @foreach ($r['statuses'] as $s)
            <x-filament::section>
                <div style="font-size:.8rem;opacity:.7;">{{ $s['label'] }}</div>
                <div style="font-size:1.75rem;font-weight:700;line-height:1.2;">{{ $s['jumlah'] }}</div>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section
        heading="Rekapitulasi per Jenis Layanan"
        description="Jumlah permohonan per layanan dan status, sesuai filter."
    >
        @if (count($r['baris']) === 0)
            <div style="opacity:.7;">Tidak ada data untuk filter ini.</div>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                    <thead>
                        <tr style="font-weight:600;">
                            <th style="{{ $sel }}text-align:left;">Kode</th>
                            <th style="{{ $sel }}text-align:left;">Jenis Layanan</th>
                            @foreach ($r['statuses'] as $s)
                                <th style="{{ $sel }}text-align:center;">{{ $s['label'] }}</th>
                            @endforeach
                            <th style="{{ $sel }}text-align:center;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($r['baris'] as $b)
                            <tr>
                                <td style="{{ $sel }}">{{ $b['kode'] }}</td>
                                <td style="{{ $sel }}">{{ $b['nama'] }}</td>
                                @foreach ($b['per'] as $n)
                                    <td style="{{ $sel }}text-align:center;">{{ $n }}</td>
                                @endforeach
                                <td style="{{ $sel }}text-align:center;font-weight:600;">{{ $b['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="font-weight:700;">
                            <td style="{{ $sel }}" colspan="2">Total</td>
                            @foreach ($r['statuses'] as $s)
                                <td style="{{ $sel }}text-align:center;">{{ $s['jumlah'] }}</td>
                            @endforeach
                            <td style="{{ $sel }}text-align:center;">{{ $r['total'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
