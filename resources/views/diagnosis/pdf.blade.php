<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Diagnosis - {{ $diagnosis->id }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #27ae60;
        }
        
        .header h1 {
            color: #27ae60;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .info-section {
            margin-bottom: 25px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #27ae60;
        }
        
        .info-section h2 {
            color: #27ae60;
            font-size: 16px;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #ddd;
        }
        
        .info-grid {
            display: table;
            width: 100%;
        }
        
        .info-row {
            display: table-row;
        }
        
        .info-label {
            display: table-cell;
            font-weight: bold;
            width: 35%;
            padding: 8px 0;
            color: #555;
        }
        
        .info-value {
            display: table-cell;
            padding: 8px 0;
            color: #333;
        }
        
        .result-section {
            margin-bottom: 25px;
            background: #fff;
            padding: 20px;
            border: 2px solid #27ae60;
            border-radius: 5px;
        }
        
        .result-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .result-header h2 {
            color: #27ae60;
            font-size: 20px;
            margin-bottom: 10px;
        }
        
        .cf-badge {
            display: inline-block;
            background: #27ae60;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .disease-info {
            margin-top: 20px;
        }
        
        .disease-info h3 {
            color: #27ae60;
            font-size: 14px;
            margin: 15px 0 8px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        
        .disease-info p {
            margin-bottom: 15px;
            text-align: justify;
            line-height: 1.8;
        }
        
        .symptoms-section {
            margin-bottom: 25px;
        }
        
        .symptoms-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .symptoms-table th {
            background: #27ae60;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 12px;
        }
        
        .symptoms-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            font-size: 11px;
        }
        
        .symptoms-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .notes-section {
            margin-bottom: 25px;
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #ffc107;
        }
        
        .notes-section h3 {
            color: #856404;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 10px;
        }
        
        .footer p {
            margin: 5px 0;
        }
        
        .no-result {
            text-align: center;
            padding: 30px;
            color: #999;
            font-style: italic;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>🌱 Laporan Diagnosis Tanaman Hias</h1>
        <p>Sistem Pakar untuk Tanaman Hias</p>
        <p style="margin-top: 5px; font-size: 11px;">Tanggal: {{ \Carbon\Carbon::parse($diagnosis->created_at)->format('d F Y, H:i') }} WIB</p>
    </div>

    <!-- User Info -->
    <div class="info-section">
        <h2>Informasi Pengguna</h2>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Nama:</div>
                <div class="info-value">{{ $user->name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Email:</div>
                <div class="info-value">{{ $user->email }}</div>
            </div>
        </div>
    </div>

    <!-- Plant Info -->
    <div class="info-section">
        <h2>Informasi Tanaman</h2>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Jenis Tanaman:</div>
                <div class="info-value">{{ $diagnosis->plant->name ?? '-' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Nama Ilmiah:</div>
                <div class="info-value">{{ $diagnosis->plant->scientific_name ?? '-' }}</div>
            </div>
        </div>
    </div>

    <!-- Diagnosis Result -->
    @php
        $ranked = $diagnosis->all_possibilities_json;
        $ranked = is_array($ranked) ? $ranked : [];
        $tiedList = is_array($tiedTopDiseases ?? null) ? $tiedTopDiseases : [];
    @endphp
    @if(count($tiedList) > 0)
        @if((float) ($tiedList[0]['certainty_value'] ?? 0) < 0.5)
        <div class="result-section" style="border-color: #f59e0b;">
            <div style="background: #fffbeb; border: 1px solid #f59e0b; border-radius: 6px; padding: 12px;">
                <strong style="color: #92400e;">Peringatan: keyakinan di bawah 50%</strong>
                <p style="margin: 8px 0 0; font-size: 11px; color: #78350f; text-align: justify;">
                    Nilai CF hipotesis utama masuk kategori diagnosis lemah. Hasil tetap ditampilkan sebagai acuan awal;
                    disarankan verifikasi tambahan atau konsultasi pakar.
                </p>
            </div>
        </div>
        @endif
        @if(count($tiedList) > 1)
        <div class="info-section" style="border-left-color: #f59e0b;">
            <h2 style="color: #b45309;">Lebih dari satu hipotesis tertinggi dengan CF yang sama</h2>
            <p style="font-size: 11px; color: #555; margin: 0;">Beberapa penyakit memiliki nilai keyakinan yang sama; ringkasan lengkap untuk masing-masing ditampilkan di bawah.</p>
        </div>
        @endif
        @foreach($tiedList as $ti => $tier)
    <div class="result-section" style="margin-bottom: 18px;">
        <div class="result-header">
            <h2>Hasil Diagnosis @if(count($tiedList) > 1) ({{ $ti + 1 }}/{{ count($tiedList) }}) @endif</h2>
            <div class="cf-badge">
                Tingkat Kepastian (CF): {{ number_format(($tier['certainty_value'] ?? 0) * 100, 1) }}%
            </div>
        </div>
        <div style="text-align: center; margin: 20px 0;">
            <h3 style="color: #27ae60; font-size: 18px; margin-bottom: 15px;">{{ $tier['name'] ?? '—' }}</h3>
        </div>
        <div class="disease-info">
            @if(!empty($tier['description']))
            <h3>📋 Deskripsi Penyakit</h3>
            <p>{{ $tier['description'] }}</p>
            @endif
            @if(!empty($tier['cause']))
            <h3>🔍 Penyebab</h3>
            <p>{{ $tier['cause'] }}</p>
            @endif
            @if(!empty($tier['solution']))
            <h3>💊 Solusi Penanganan</h3>
            <p>{{ $tier['solution'] }}</p>
            @endif
            @if(!empty($tier['prevention']))
            <h3>🛡️ Pencegahan</h3>
            <p>{{ $tier['prevention'] }}</p>
            @endif
            <p style="font-size: 10px; color: #888; margin-top: 12px;">Gejala cocok: {{ $tier['matched_symptoms_count'] ?? 0 }}</p>
        </div>
    </div>
        @endforeach
        @if(count($ranked) > 0)
    <div class="result-section">
        <div style="padding-top: 8px;">
            <h3 style="color: #27ae60; font-size: 14px; margin-bottom: 10px;">Peringkat hipotesis (nilai CF)</h3>
            <p style="font-size: 10px; color: #666; margin-bottom: 10px;">Termasuk hipotesis dengan CF di bawah 50% sebagai informasi probabilitas relatif.</p>
            <table class="symptoms-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Penyakit</th>
                        <th>Gejala cocok</th>
                        <th>CF</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ranked as $idx => $row)
                    <tr>
                        <td style="text-align: center;">{{ $idx + 1 }}</td>
                        <td>{{ $row['disease_name'] ?? '—' }}</td>
                        <td style="text-align: center;">{{ $row['matched_count'] ?? 0 }}</td>
                        <td style="text-align: center;">
                            {{ number_format(($row['certainty_value'] ?? 0) * 100, 1) }}%
                            @if(($row['certainty_value'] ?? 0) < 0.5)
                            <span style="font-size: 9px; color: #b45309;"> (di bawah 50%)</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
        @endif
    @elseif($diagnosis->disease)
    <div class="result-section">
        @if((float) $diagnosis->certainty_value < 0.5)
        <div style="background: #fffbeb; border: 1px solid #f59e0b; border-radius: 6px; padding: 12px; margin-bottom: 16px;">
            <strong style="color: #92400e;">Peringatan: keyakinan di bawah 50%</strong>
            <p style="margin: 8px 0 0; font-size: 11px; color: #78350f; text-align: justify;">
                Nilai CF hipotesis utama masuk kategori diagnosis lemah. Hasil tetap ditampilkan sebagai acuan awal;
                disarankan verifikasi tambahan atau konsultasi pakar.
            </p>
        </div>
        @endif
        <div class="result-header">
            <h2>Hasil Diagnosis</h2>
            <div class="cf-badge">
                Tingkat Kepastian (CF): {{ number_format($diagnosis->certainty_value * 100, 1) }}%
            </div>
        </div>
        <div style="text-align: center; margin: 20px 0;">
            <h3 style="color: #27ae60; font-size: 18px; margin-bottom: 15px;">{{ $diagnosis->disease->name }}</h3>
        </div>
        <div class="disease-info">
            @if($diagnosis->disease->description)
            <h3>📋 Deskripsi Penyakit</h3>
            <p>{{ $diagnosis->disease->description }}</p>
            @endif
            @if($diagnosis->disease->cause)
            <h3>🔍 Penyebab</h3>
            <p>{{ $diagnosis->disease->cause }}</p>
            @endif
            @if($diagnosis->disease->solution)
            <h3>💊 Solusi Penanganan</h3>
            <p>{{ $diagnosis->disease->solution }}</p>
            @endif
            @if($diagnosis->disease->prevention)
            <h3>🛡️ Pencegahan</h3>
            <p>{{ $diagnosis->disease->prevention }}</p>
            @endif
        </div>
        @if(count($ranked) > 0)
        <div style="margin-top: 22px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
            <h3 style="color: #27ae60; font-size: 14px; margin-bottom: 10px;">Peringkat hipotesis (nilai CF)</h3>
            <p style="font-size: 10px; color: #666; margin-bottom: 10px;">Termasuk hipotesis dengan CF di bawah 50% sebagai informasi probabilitas relatif.</p>
            <table class="symptoms-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Penyakit</th>
                        <th>Gejala cocok</th>
                        <th>CF</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ranked as $idx => $row)
                    <tr>
                        <td style="text-align: center;">{{ $idx + 1 }}</td>
                        <td>{{ $row['disease_name'] ?? '—' }}</td>
                        <td style="text-align: center;">{{ $row['matched_count'] ?? 0 }}</td>
                        <td style="text-align: center;">
                            {{ number_format(($row['certainty_value'] ?? 0) * 100, 1) }}%
                            @if(($row['certainty_value'] ?? 0) < 0.5)
                            <span style="font-size: 9px; color: #b45309;"> (di bawah 50%)</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
    @elseif($diagnosis->status === 'pending')
    <div class="result-section">
        <div class="no-result">
            <p><strong>Hasil belum siap</strong></p>
            <p style="margin-top: 10px; font-size: 11px;">Diagnosis masih dalam proses. Muat ulang laporan setelah mesin sistem pakar selesai.</p>
        </div>
    </div>
    @elseif($diagnosis->status === 'completed')
    <div class="result-section" style="border-color: #f59e0b;">
        <div class="no-result" style="color: #444; font-style: normal;">
            <p><strong>Tidak ada hipotesis penyakit</strong></p>
            <p style="margin-top: 10px; font-size: 11px; text-align: justify;">
                Tidak ada aturan dalam basis pengetahuan yang cocok dengan gejala yang dipilih, sehingga tidak ada nilai CF yang dihitung.
            </p>
            @if($diagnosis->recommendation)
            <p style="margin-top: 12px; font-size: 11px; text-align: justify; font-style: italic;">{{ $diagnosis->recommendation }}</p>
            @endif
        </div>
    </div>
    @else
    <div class="result-section">
        <div class="no-result">
            <p>Belum ada hasil diagnosis</p>
            <p style="margin-top: 10px; font-size: 11px;">Status: {{ ucfirst($diagnosis->status) }}</p>
        </div>
    </div>
    @endif

    <!-- Symptoms -->
    @if($diagnosis->symptoms && count($diagnosis->symptoms) > 0)
    <div class="symptoms-section">
        <div class="info-section">
            <h2>Gejala yang Dipilih</h2>
            <table class="symptoms-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Deskripsi Gejala</th>
                        <th>Tingkat Kepastian User</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($diagnosis->symptoms as $symptom)
                    <tr>
                        <td><strong>{{ $symptom->code }}</strong></td>
                        <td>{{ $symptom->description }}</td>
                        <td style="text-align: center;">{{ number_format(($symptom->pivot->user_cf ?? 0) * 100, 0) }}%</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- User Notes -->
    @if($diagnosis->user_notes)
    <div class="notes-section">
        <h3>📝 Catatan Tambahan</h3>
        <p>{{ $diagnosis->user_notes }}</p>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p><strong>System Pakar untuk Tanaman Hias</strong></p>
        <p>Laporan ini dihasilkan secara otomatis oleh sistem</p>
        <p>ID Diagnosis: {{ $diagnosis->id }} | Status: {{ ucfirst($diagnosis->status) }}</p>
        <p style="margin-top: 10px;">© {{ date('Y') }} System Pakar. All rights reserved.</p>
    </div>
</body>
</html>
