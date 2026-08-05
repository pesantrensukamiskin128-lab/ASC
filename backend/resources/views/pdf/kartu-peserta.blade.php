<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Kartu Peserta PMB - {{ $registrant->registration_number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            color: #1a1a1a;
            margin: 0;
            padding: 20px;
        }
        .card {
            width: 100%;
            border: 2px solid #1e40af;
            border-collapse: collapse;
        }
        /* HEADER */
        .card-header td {
            background: #1e40af;
            color: white;
            padding: 10px 14px;
            vertical-align: middle;
        }
        .logo-img {
            width: 36px;
            height: 36px;
            border-radius: 4px;
        }
        .institution-name {
            font-size: 11px;
            font-weight: bold;
        }
        .institution-entity {
            font-size: 7.5px;
            opacity: 0.8;
        }
        .header-title {
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-align: right;
        }
        .header-period {
            font-size: 7.5px;
            opacity: 0.8;
            text-align: right;
        }
        /* BODY */
        .card-body td {
            padding: 14px;
            vertical-align: top;
        }
        .photo-cell {
            width: 75px;
            padding-right: 12px !important;
        }
        .photo-img {
            width: 75px;
            height: 100px;
            object-fit: cover;
            border: 1px solid #d1d5db;
            border-radius: 3px;
        }
        .photo-placeholder {
            width: 75px;
            height: 100px;
            border: 1px solid #d1d5db;
            border-radius: 3px;
            background: #f3f4f6;
            text-align: center;
            line-height: 100px;
            font-size: 24px;
            color: #9ca3af;
            font-weight: bold;
        }
        .reg-number {
            font-size: 11px;
            font-family: 'DejaVu Sans Mono', monospace;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 6px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 2px 0;
            font-size: 8.5px;
            vertical-align: top;
        }
        .info-table .lbl {
            width: 90px;
            color: #6b7280;
        }
        .info-table .sep {
            width: 6px;
            color: #9ca3af;
        }
        .info-table .val {
            color: #111827;
            font-weight: bold;
        }
        .info-table .val-light {
            color: #374151;
        }
        /* FOOTER */
        .card-footer td {
            background: #f3f4f6;
            border-top: 1px solid #e5e7eb;
            padding: 8px 14px;
            vertical-align: middle;
            font-size: 7px;
            color: #6b7280;
        }
        .footer-right {
            text-align: right;
            font-size: 7.5px;
        }
        .footer-right strong {
            color: #374151;
        }
    </style>
</head>
<body>
    <table class="card" cellpadding="0" cellspacing="0">
        <!-- HEADER ROW -->
        <tr>
            <td class="card-header" colspan="2">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="40" style="vertical-align:middle; padding-right:8px;">
                            @if($institution && $institution->logo_path)
                                <img class="logo-img" src="{{ storage_path('app/public/' . $institution->logo_path) }}" alt="Logo">
                            @else
                                <div style="width:36px;height:36px;border-radius:4px;background:rgba(255,255,255,0.2);text-align:center;line-height:36px;font-size:16px;font-weight:bold;color:white;">
                                    {{ strtoupper(substr($institution->name ?? 'U', 0, 1)) }}
                                </div>
                            @endif
                        </td>
                        <td style="vertical-align:middle;">
                            <div class="institution-name">{{ $institution->name ?? 'Universitas' }}</div>
                            <div class="institution-entity">{{ $institution->legal_entity_name ?? '' }}</div>
                        </td>
                        <td style="vertical-align:middle; text-align:right; width:140px;">
                            <div class="header-title">Kartu Peserta</div>
                            <div class="header-period">{{ $registrant->period->name ?? '' }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- BODY ROW -->
        <tr>
            <td class="card-body" colspan="2">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <!-- Foto -->
                        <td class="photo-cell">
                            @if($registrant->photo_path)
                                <img class="photo-img" src="{{ public_path('storage/' . $registrant->photo_path) }}" alt="Foto">
                            @else
                                <div class="photo-placeholder">{{ strtoupper(substr($registrant->full_name, 0, 1)) }}</div>
                            @endif
                        </td>
                        <!-- Data -->
                        <td style="vertical-align:top;">
                            <div class="reg-number">{{ $registrant->registration_number }}</div>
                            <table class="info-table" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td class="lbl">Nama Lengkap</td>
                                    <td class="sep">:</td>
                                    <td class="val">{{ $registrant->full_name }}</td>
                                </tr>
                                <tr>
                                    <td class="lbl">Tempat, Tgl Lahir</td>
                                    <td class="sep">:</td>
                                    <td class="val-light">{{ $registrant->birth_place ?? '-' }}, {{ $registrant->birth_date ? $registrant->birth_date->format('d F Y') : '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="lbl">Jenis Kelamin</td>
                                    <td class="sep">:</td>
                                    <td class="val-light">{{ $registrant->gender === 'L' ? 'Laki-laki' : 'Perempuan' }}</td>
                                </tr>
                                <tr>
                                    <td class="lbl">Asal Sekolah</td>
                                    <td class="sep">:</td>
                                    <td class="val-light">{{ $registrant->school_name ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="lbl">Jalur Pendaftaran</td>
                                    <td class="sep">:</td>
                                    <td class="val">{{ $registrant->path->name ?? 'Reguler' }}</td>
                                </tr>
                                <tr>
                                    <td class="lbl">Pilihan I</td>
                                    <td class="sep">:</td>
                                    <td class="val">{{ $registrant->studyProgramChoice1->name ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="lbl">Pilihan II</td>
                                    <td class="sep">:</td>
                                    <td class="val-light">{{ $registrant->studyProgramChoice2->name ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="lbl">Pilihan III</td>
                                    <td class="sep">:</td>
                                    <td class="val-light">{{ $registrant->studyProgramChoice3->name ?? '-' }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- FOOTER ROW -->
        <tr>
            <td class="card-footer" style="width:60%;">
                Kartu ini wajib dibawa saat mengikuti seleksi.<br>
                Tidak berlaku tanpa pas foto asli.
            </td>
            <td class="card-footer footer-right" style="width:40%;">
                @if($registrant->period && $registrant->period->selection_date)
                    <strong>Tanggal Seleksi</strong><br>
                    {{ $registrant->period->selection_date->format('d F Y') }}
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
