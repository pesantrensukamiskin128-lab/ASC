<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Surat - {{ $letter->letter_number ?? 'Draft' }}</title>
    <style>
        @page { margin: 5mm 10mm 10mm 10mm; }
        body { font-family: 'Times New Roman', serif; font-size: 12pt; margin: 0; padding: 0; line-height: 1.15; }
        .letterhead { text-align: center; margin-bottom: 3px; }
        .letterhead img { width: 100%; }
        .letter-info { margin-top: 8px; font-size: 12pt; line-height: 1.15; padding: 0 40px; }
        .letter-info table { border: none; width: auto; }
        .letter-info td { padding: 0; vertical-align: top; border: none; line-height: 1.15; }
        .letter-info .label { width: 75px; }
        .recipient-block { margin-top: 8px; font-size: 12pt; line-height: 1.15; padding: 0 40px; }
        .recipient-block p { margin: 0; }
        .letter-body { margin-top: 8px; text-align: justify; font-size: 12pt; line-height: 1.15; padding: 0 40px; }
        .letter-body p { margin: 2px 0; }
        .letter-body table { width: 100%; border-collapse: collapse; margin: 4px 0; }
        .letter-body table td, .letter-body table th { border: 1px solid #333; padding: 2px 5px; font-size: 11pt; line-height: 1.15; }
        .letter-body table[data-borderless="true"] td,
        .letter-body table[data-borderless="true"] th { border: none; }
        .letter-body ul { list-style-type: disc; padding-left: 20px; margin: 2px 0; }
        .letter-body ol { list-style-type: decimal; padding-left: 20px; margin: 2px 0; }
        .letter-body li { margin: 0; }
        .sign-wrapper { margin-top: 15px; width: 100%; padding: 0 40px; }
        .sign-area { float: right; width: 240px; text-align: left; }
        .sign-area p { margin: 0; font-size: 11pt; line-height: 1.2; }
        .sign-area .qr { margin: 3px 0; }
        .sign-area .name { font-weight: bold; text-decoration: underline; margin-top: 2px; }
        .sign-area .nidn { font-size: 10pt; }
        .verify-footer { position: fixed; bottom: 0; left: 0; right: 0; padding: 5px 0 0; border-top: 1px solid #d1d5db; }
        .verify-footer table { width: 100%; border-collapse: collapse; }
        .verify-footer td { border: none; padding: 0; vertical-align: middle; }
        .verify-text { font-size: 10pt; color: #555; line-height: 1.2; padding-left: 6px; }
        .watermark { position: fixed; top: 40%; left: 10%; transform: rotate(-35deg); font-size: 80pt; color: rgba(200,200,200,0.25); font-weight: bold; z-index: -1; letter-spacing: 10px; }
    </style>
</head>
<body>
    @if(!$isFinal)
    <div class="watermark">DRAFT</div>
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
            <tr><td class="label">Perihal</td><td>: <em>{{ $letter->subject }}</em></td></tr>
        </table>
    </div>

    {{-- Tujuan --}}
    <div class="recipient-block">
        <p>Kepada Yang Terhormat,</p>
        <p>{!! nl2br(e($letter->recipient)) !!}</p>
        <p>di-</p>
        <p style="padding-left: 20px;">Tempat</p>
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
                <img src="{{ $qrSignature }}" width="50" height="50" alt="QR">
            </div>
            @else
            <br><br><br>
            @endif
            <p class="name">{{ $signerName }}</p>
            @if($signerNidn)
                <p class="nidn">NIDN: {{ $signerNidn }}</p>
            @endif
        </div>
    </div>

    {{-- Footer Verifikasi --}}
    @if($isFinal)
    <div class="verify-footer">
        <table>
            <tr>
                <td style="width: 60px;">
                    <img src="{{ $qrFooter }}" width="60" height="60" alt="QR Verifikasi">
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
