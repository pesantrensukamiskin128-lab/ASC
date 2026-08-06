<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Kalender Akademik</title>
    <style>
        @page { margin: 1cm 2cm 2cm 2cm; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 0; position: relative; min-height: 100%; }
        .letterhead { text-align: center; margin-bottom: 5px; }
        .letterhead img { width: 100%; max-height: 130px; }
        .letterhead-text { text-align: center; margin-bottom: 5px; }
        .letterhead-text h1 { font-size: 16px; margin: 0; text-transform: uppercase; }
        .letterhead-text h2 { font-size: 13px; margin: 2px 0; font-weight: normal; }
        .letterhead-text p { font-size: 10px; margin: 2px 0; color: #555; }
        .title { text-align: center; font-size: 14px; font-weight: bold; margin-bottom: 5px; margin-top: 15px; }
        .subtitle { text-align: center; font-size: 11px; color: #555; margin-bottom: 20px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data th { background-color: #2563eb; color: white; padding: 8px 6px; text-align: left; font-size: 10px; }
        table.data td { padding: 6px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
        table.data tr:nth-child(even) td { background-color: #f9fafb; }
        .category-badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; }
        .cat-akademik { background: #dbeafe; color: #1d4ed8; }
        .cat-uts { background: #fef3c7; color: #92400e; }
        .cat-uas { background: #fee2e2; color: #991b1b; }
        .cat-libur { background: #d1fae5; color: #065f46; }
        .cat-kkn { background: #ede9fe; color: #5b21b6; }
        .cat-wisuda { background: #fce7f3; color: #9d174d; }
        .cat-lainnya { background: #f3f4f6; color: #374151; }

        .sign-wrapper { margin-top: 30px; width: 100%; }
        .sign-area { float: right; width: 250px; text-align: left; }
        .sign-area p { margin: 2px 0; font-size: 11px; }
        .sign-area .qr { margin: 5px 0; }
        .sign-area .name { font-weight: bold; text-decoration: underline; margin-top: 3px; }

        .verify-footer { position: fixed; bottom: 0; left: 0; right: 0; padding: 12px 0 0; border-top: 1px solid #e5e7eb; }
        .verify-footer table { width: 100%; border-collapse: collapse; }
        .verify-footer td { border: none; padding: 0; vertical-align: middle; }
        .verify-text { font-size: 8px; color: #666; line-height: 1.4; padding-left: 8px; }
    </style>
</head>
<body>
    {{-- Kop Surat --}}
    @if($letterheadUrl && file_exists($letterheadUrl))
        <div class="letterhead">
            <img src="{{ $letterheadUrl }}" alt="Kop Surat">
        </div>
    @elseif($institution)
        <div class="letterhead-text">
            <h1>{{ $institution->name }}</h1>
            @if($institution->legal_entity_name)
                <h2>{{ $institution->legal_entity_name }}</h2>
            @endif
            <p>{{ $institution->address }}</p>
            <p>
                @if($institution->phone) Telp: {{ $institution->phone }} @endif
                @if($institution->email) | Email: {{ $institution->email }} @endif
                @if($institution->website) | {{ $institution->website }} @endif
            </p>
        </div>
    @endif

    <div class="title">KALENDER AKADEMIK</div>
    <div class="subtitle">{{ $academicYear }}</div>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 30px;">No</th>
                <th>Kegiatan</th>
                <th style="width: 90px;">Tanggal Mulai</th>
                <th style="width: 90px;">Tanggal Selesai</th>
                <th style="width: 70px;">Kategori</th>
            </tr>
        </thead>
        <tbody>
            @foreach($events as $i => $event)
            <tr>
                <td style="text-align: center;">{{ $i + 1 }}</td>
                <td>
                    <strong>{{ $event->title }}</strong>
                    @if($event->description)
                        <br><span style="color: #666;">{{ $event->description }}</span>
                    @endif
                </td>
                <td>{{ $event->start_date?->format('d/m/Y') }}</td>
                <td>{{ $event->end_date?->format('d/m/Y') ?? '-' }}</td>
                <td>
                    <span class="category-badge cat-{{ strtolower($event->category) }}">
                        {{ $event->category }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Tanda Tangan --}}
    <div class="sign-wrapper">
        <div class="sign-area">
            <p>Bandung, {{ now()->translatedFormat('d F Y') }}</p>
            <p>Wakil Ketua I Bidang Akademik</p>
            <div class="qr">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=50x50&data={{ urlencode($verifyUrl) }}" width="50" height="50" alt="QR">
            </div>
            <p class="name">{{ $wk1Name }}</p>
        </div>
    </div>

    {{-- Footer Verifikasi (fixed di bawah halaman) --}}
    <div class="verify-footer">
        <table>
            <tr>
                <td style="width: 60px;">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=60x60&data={{ urlencode($verifyUrl) }}" width="60" height="60" alt="QR Verifikasi">
                </td>
                <td>
                    <div class="verify-text">
                        Dokumen ini telah ditandatangani dan distempel secara elektronik melalui aplikasi Al-Jawami Smart Campus, scan QR Code untuk verifikasi.
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
