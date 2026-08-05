<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Kalender Akademik</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; }
        .letterhead { text-align: center; margin-bottom: 10px; }
        .letterhead img { max-width: 100%; max-height: 120px; }
        .letterhead-text { text-align: center; margin-bottom: 5px; }
        .letterhead-text h1 { font-size: 16px; margin: 0; text-transform: uppercase; }
        .letterhead-text h2 { font-size: 13px; margin: 2px 0; font-weight: normal; }
        .letterhead-text p { font-size: 10px; margin: 2px 0; color: #555; }
        .divider { border-bottom: 3px double #000; margin: 10px 0 20px; }
        .title { text-align: center; font-size: 14px; font-weight: bold; margin-bottom: 5px; }
        .subtitle { text-align: center; font-size: 11px; color: #555; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #2563eb; color: white; padding: 8px 6px; text-align: left; font-size: 10px; }
        td { padding: 6px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
        tr:nth-child(even) td { background-color: #f9fafb; }
        .category-badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; }
        .cat-akademik { background: #dbeafe; color: #1d4ed8; }
        .cat-uts { background: #fef3c7; color: #92400e; }
        .cat-uas { background: #fee2e2; color: #991b1b; }
        .cat-libur { background: #d1fae5; color: #065f46; }
        .cat-kkn { background: #ede9fe; color: #5b21b6; }
        .cat-wisuda { background: #fce7f3; color: #9d174d; }
        .footer { margin-top: 30px; text-align: right; font-size: 10px; color: #666; }
        .sign-area { margin-top: 40px; text-align: right; padding-right: 40px; }
        .sign-area p { margin: 2px 0; font-size: 11px; }
    </style>
</head>
<body>
    {{-- Kop Surat --}}
    @if($letterheadUrl && file_exists($letterheadUrl))
        <div class="letterhead">
            <img src="{{ $letterheadUrl }}" alt="Kop Surat">
        </div>
        <div class="divider"></div>
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
        <div class="divider"></div>
    @endif

    <div class="title">KALENDER AKADEMIK</div>
    <div class="subtitle">{{ $academicYear }}</div>

    <table>
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

    @if($institution)
    <div class="sign-area">
        <p>{{ $institution->address ? explode(',', $institution->address)[0] : '' }}, {{ now()->translatedFormat('d F Y') }}</p>
        <p>Wakil Ketua I Bidang Akademik</p>
        <br><br><br>
        <p>_________________________</p>
    </div>
    @endif

    <div class="footer">
        Dicetak pada: {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
