<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Surat - {{ $letter->letter_number ?? 'Draft' }}</title>
    <style>
        @page { margin: 10mm 20mm 20mm 20mm; }
        body { font-family: 'Times New Roman', serif; font-size: 12pt; margin: 0; padding: 0; line-height: 1.5; }
        .letterhead { text-align: center; margin-bottom: 5px; }
        .letterhead img { width: 100%; max-height: 130px; }
        .letter-info { margin-top: 15px; font-size: 11pt; }
        .letter-info table { border: none; }
        .letter-info td { padding: 1px 0; vertical-align: top; border: none; }
        .letter-info .label { width: 100px; }
        .letter-body { margin-top: 20px; text-align: justify; font-size: 12pt; }
        .letter-body p { margin: 6px 0; }
        .letter-body table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        .letter-body table td, .letter-body table th { border: 1px solid #333; padding: 4px 8px; font-size: 11pt; }
        .letter-body table[data-borderless="true"] td,
        .letter-body table[data-borderless="true"] th { border: none; }
        .letter-body ul { list-style-type: disc; padding-left: 24px; }
        .letter-body ol { list-style-type: decimal; padding-left: 24px; }
        .sign-wrapper { margin-top: 30px; width: 100%; }
        .sign-area { float: right; width: 250px; text-align: left; }
        .sign-area p { margin: 2px 0; font-size: 11pt; }
        .sign-area .qr { margin: 5px 0; }
        .sign-area .name { font-weight: bold; text-decoration: underline; margin-top: 3px; }
        .sign-area .nidn { font-size: 10pt; }
        .verify-footer { position: fixed; bottom: 0; left: 0; right: 0; padding: 10px 0 0; border-top: 1px solid #e5e7eb; }
        .verify-footer table { width: 100%; border-collapse: collapse; }
        .verify-footer td { border: none; padding: 0; vertical-align: middle; }
        .verify-text { font-size: 8px; color: #666; line-height: 1.4; padding-left: 8px; }
    </style>
</head>
<body>
    {{-- Watermark DRAFT jika belum final --}}
    @if(!$isFinal)
    <div style="position: fixed; top: 40%; left: 10%; transform: rotate(-35deg); font-size: 80pt; color: rgba(200,200,200,0.3); font-weight: bold; z-index: -1; letter-spacing: 10px;">
        DRAFT
    </div>
    @endif

    {{-- Kop Surat --}}
    @if($letterheadUrl && file_exists($letterheadUrl))
        <div class="letterhead">
            <img src="{{ $letterheadUrl }}" alt="Kop Surat">
        </div>
    @endif

    {{-- Info Surat --}}
    <div class="letter-info">
        <table>
            <tr><td class="label">Nomor</td><td>: {{ $letter->letter_number ?? '-' }}</td></tr>
            <tr><td class="label">Lampiran</td><td>: {{ $letter->attachment_note ?? '-' }}</td></tr>
            <tr><td class="label">Perihal</td><td>: <strong>{{ $letter->subject }}</strong></td></tr>
        </table>
    </div>

    {{-- Tujuan --}}
    <div style="margin-top: 15px; font-size: 12pt;">
        <p>Kepada Yth.<br>{!! nl2br(e($letter->recipient)) !!}</p>
        <p>di Tempat</p>
    </div>

    {{-- Isi Surat --}}
    <div class="letter-body">
        {!! $letter->body !!}
    </div>

    {{-- Tanda Tangan --}}
    <div class="sign-wrapper">
        <div class="sign-area">
            <p>{{ $letter->city ?? 'Bandung' }}, {{ $letter->letter_date?->translatedFormat('d F Y') }}</p>
            <p>{{ $signerPosition }}</p>
            @if($isFinal)
            <div class="qr">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=50x50&data={{ urlencode($verifyUrl) }}" width="50" height="50" alt="QR">
            </div>
            @else
            <br><br>
            @endif
            <p class="name">{{ $signerName }}</p>
            @if($signerNidn)
                <p class="nidn">NIDN: {{ $signerNidn }}</p>
            @endif
        </div>
    </div>

    {{-- Footer Verifikasi (hanya di surat final) --}}
    @if($isFinal)
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
    @endif
</body>
</html>
