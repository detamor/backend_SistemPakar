<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Diagnosis (Guest)</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #222; }
        .h1 { font-size: 18px; font-weight: 700; margin: 0 0 6px; color: #14532d; }
        .muted { color: #555; font-size: 11px; margin: 0; }
        .card { border: 1px solid #d1d5db; border-radius: 10px; padding: 14px; margin-top: 14px; }
        .row { display: table; width: 100%; }
        .cell { display: table-cell; vertical-align: top; }
        .label { width: 40%; font-weight: 700; color: #374151; padding: 6px 0; }
        .value { width: 60%; padding: 6px 0; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; background: #dcfce7; color: #14532d; font-weight: 700; }
        .section-title { font-weight: 800; font-size: 13px; color: #14532d; margin: 16px 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; font-size: 11px; }
        th { background: #f3f4f6; text-align: left; }
        .footer { margin-top: 18px; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <div>
        <div class="h1">Hasil Diagnosis (tanpa login)</div>
        <p class="muted">Laporan ringkas. Hasil tidak tersimpan ke riwayat.</p>
        <p class="muted">Dibuat pada: {{ optional($data['generated_at'] ?? null)->format('d/m/Y H:i') ?? '-' }}</p>
    </div>

    <div class="card">
        <div class="row">
            <div class="cell label">Tanaman</div>
            <div class="cell value">{{ $data['plant_name'] ?? '-' }}</div>
        </div>
        <div class="row">
            <div class="cell label">Diagnosis</div>
            <div class="cell value">{{ $data['disease_name'] ?? 'Tidak ada hipotesis' }}</div>
        </div>
        <div class="row">
            <div class="cell label">Nilai CF</div>
            @php
                $mainCf = (float) ($data['certainty_value'] ?? 0);
                $mainLabel = $mainCf >= 0.7 ? 'Kepastian Tinggi' : ($mainCf >= 0.5 ? 'Kepastian Sedang' : ($mainCf >= 0.4 ? 'Cukup Rendah' : 'Kepastian Rendah'));
            @endphp
            <div class="cell value">
                <span class="badge">{{ number_format($mainCf * 100, 1) }}%</span>
                <span style="font-size: 10px; font-weight: 800; color: #374151; margin-left: 8px;">{{ $mainLabel }}</span>
            </div>
        </div>
        <div class="row">
            <div class="cell label">Gejala Cocok</div>
            <div class="cell value">{{ (int) ($data['matched_symptoms_count'] ?? 0) }}</div>
        </div>
    </div>

    @php
        $poss = is_array($data['all_possibilities'] ?? null) ? $data['all_possibilities'] : [];
        $high = is_array($data['high_confidence_diseases'] ?? null) ? $data['high_confidence_diseases'] : [];
    @endphp

    @if(count($poss) > 0)
        <div class="section-title">Peringkat Hipotesis</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 52px;">#</th>
                    <th>Penyakit</th>
                    <th style="width: 90px;">CF</th>
                    <th style="width: 140px;">Label</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_slice($poss, 0, 10) as $i => $row)
                    @php
                        $cf = (float) ($row['certainty_value'] ?? 0);
                        $label = $cf >= 0.7 ? 'Kepastian Tinggi' : ($cf >= 0.5 ? 'Kepastian Sedang' : ($cf >= 0.4 ? 'Cukup Rendah' : 'Kepastian Rendah'));
                    @endphp
                    <tr>
                        <td style="text-align:center;">{{ $i + 1 }}</td>
                        <td>{{ $row['disease_name'] ?? '-' }}</td>
                        <td style="text-align:center;">{{ number_format(((float) ($row['certainty_value'] ?? 0)) * 100, 1) }}%</td>
                        <td style="text-align:center;">{{ $label }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(count($high) > 0)
        <div class="section-title">Detail Hasil (≥ 70%)</div>
        @foreach($high as $d)
            @php
                $cf = (float) ($d['certainty_value'] ?? 0);
                $label = $cf >= 0.7 ? 'Kepastian Tinggi' : ($cf >= 0.5 ? 'Kepastian Sedang' : ($cf >= 0.4 ? 'Cukup Rendah' : 'Kepastian Rendah'));
            @endphp
            <div class="card">
                <div class="row">
                    <div class="cell label">Penyakit</div>
                    <div class="cell value">{{ $d['name'] ?? '-' }}</div>
                </div>
                <div class="row">
                    <div class="cell label">Nilai CF</div>
                    <div class="cell value">
                        <span class="badge">{{ number_format($cf * 100, 1) }}%</span>
                        <span style="font-size: 10px; font-weight: 800; color: #374151; margin-left: 8px;">{{ $label }}</span>
                    </div>
                </div>
                <div class="row">
                    <div class="cell label">Gejala Cocok</div>
                    <div class="cell value">{{ (int) ($d['matched_symptoms_count'] ?? 0) }}</div>
                </div>
                @if(!empty($d['description']))
                    <div style="margin-top: 10px;">
                        <div style="font-weight: 800; color: #14532d; margin-bottom: 4px;">Deskripsi</div>
                        <div style="font-size: 11px; line-height: 1.6; color: #1f2937;">{{ $d['description'] }}</div>
                    </div>
                @endif
                @if(!empty($d['cause']))
                    <div style="margin-top: 10px;">
                        <div style="font-weight: 800; color: #14532d; margin-bottom: 4px;">Penyebab</div>
                        <div style="font-size: 11px; line-height: 1.6; color: #1f2937;">{{ $d['cause'] }}</div>
                    </div>
                @endif
                @if(!empty($d['solution']))
                    <div style="margin-top: 10px;">
                        <div style="font-weight: 800; color: #14532d; margin-bottom: 4px;">Solusi</div>
                        <div style="font-size: 11px; line-height: 1.6; color: #1f2937;">{{ $d['solution'] }}</div>
                    </div>
                @endif
                @if(!empty($d['prevention']))
                    <div style="margin-top: 10px;">
                        <div style="font-weight: 800; color: #14532d; margin-bottom: 4px;">Pencegahan</div>
                        <div style="font-size: 11px; line-height: 1.6; color: #1f2937;">{{ $d['prevention'] }}</div>
                    </div>
                @endif
            </div>
        @endforeach
    @endif

    @if(!empty($data['user_notes']))
        <div class="section-title">Catatan Tambahan</div>
        <div class="card">
            {{ $data['user_notes'] }}
        </div>
    @endif

    <div class="footer">
        System Pakar Tanaman Hias — PDF ringkas untuk pengguna tanpa login.
    </div>
</body>
</html>
