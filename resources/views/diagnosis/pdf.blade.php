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
            <div class="info-row">
                <div class="info-label">Nomor WhatsApp:</div>
                <div class="info-value">{{ $user->whatsapp_number ?? '-' }}</div>
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
    @if($diagnosis->disease)
    <div class="result-section">
        <div class="result-header">
            <h2>Hasil Diagnosis</h2>
            <div class="cf-badge">
                Tingkat Kepastian: {{ number_format($diagnosis->certainty_value * 100, 1) }}%
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
    </div>
    @else
    <div class="result-section">
        <div class="no-result">
            <p>Belum ada hasil diagnosis</p>
            <p style="margin-top: 10px; font-size: 11px;">Diagnosis masih dalam proses atau belum selesai</p>
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

