<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>KRS - {{ $krs->student?->nim }}</title>
    <style>
        @page { margin: 10mm 15mm 20mm 15mm; }
        body { font-family: 'Times New Roman', serif; font-size: 11pt; margin: 0; padding: 0; line-height: 1.5; position: relative; }
        table { width: 100%; border-collapse: collapse; }
        .bordered td, .bordered th { border: 1px solid #000; padding: 5px 8px; vertical-align: top; }
        .bordered th { font-weight: bold; text-align: center; }
        .header-table { border: 1px solid #000; }
        .header-table td { padding: 10px; vertical-align: middle; border: none; }
        .header-logo { width: 80px; text-align: center; border-right: 1px solid #000 !important; }
        .header-logo img { max-width: 65px; max-height: 65px; }
        .header-text { font-size: 14pt; font-weight: bold; }
        .title-row { border: 1px solid #000; border-top: none; }
        .title-row td { text-align: center; padding: 8px; font-size: 13pt; font-weight: bold; }
        .info-table td { padding: 3px 8px; font-size: 11pt; border: none; }
        .info-label { width: 140px; font-weight: bold; }
        .sign-table td { padding: 8px 10px; text-align: center; vertical-align: top; border: none; width: 33%; }
        .sign-table img { width: 50px; height: 50px; }
        .sign-name { font-weight: bold; text-decoration: underline; font-size: 10pt; }
        .sign-role { font-size: 9pt; }
    </style>
</head>
<body>

{{-- HEADER --}}
@php
    $letterheadPath = $institution?->letterhead_path ? storage_path('app/public/' . $institution->letterhead_path) : null;
@endphp

@if($letterheadPath && file_exists($letterheadPath))
    <div style="text-align:center; margin-bottom:10px;">
        <img src="{{ $letterheadPath }}" alt="Kop Surat" style="width:100%;">
    </div>
@else
    <table class="header-table">
        <tr>
            <td class="header-logo">
                @if($logoPath && file_exists($logoPath))
                    <img src="{{ $logoPath }}" alt="Logo">
                @endif
            </td>
            <td class="header-text">
                {{ strtoupper($institution?->name ?? 'PERGURUAN TINGGI') }}<br>
                @if($krs->student?->studyProgram?->faculty)
                    {{ strtoupper($krs->student->studyProgram->faculty->name) }}<br>
                @endif
                PROGRAM STUDI {{ strtoupper($krs->student?->studyProgram?->name ?? '') }}
            </td>
        </tr>
    </table>
@endif
<div style="text-align:center; padding:8px 0; font-size:13pt; font-weight:bold;">
    KARTU RENCANA STUDI (KRS)
</div>

{{-- INFO MAHASISWA --}}
<table class="info-table" style="margin-top: 15px;">
    <tr><td class="info-label">NIM</td><td>: {{ $krs->student?->nim }}</td></tr>
    <tr><td class="info-label">Nama Mahasiswa</td><td>: {{ $krs->student?->name }}</td></tr>
    <tr><td class="info-label">Program Studi</td><td>: {{ $krs->student?->studyProgram?->name }}</td></tr>
    <tr><td class="info-label">Semester</td><td>: {{ $krs->semester?->name }}</td></tr>
    <tr><td class="info-label">Dosen Wali</td><td>: {{ $krs->advisor?->display_name ?? $krs->advisor?->full_name ?? '-' }}</td></tr>
    <tr><td class="info-label">Total SKS</td><td>: {{ $krs->total_credits }} SKS</td></tr>
</table>

{{-- TABEL MK --}}
<table class="bordered" style="margin-top: 15px;">
    <thead>
        <tr>
            <th style="width:30px;">No</th>
            <th style="width:80px;">Kode MK</th>
            <th>Mata Kuliah</th>
            <th style="width:40px;">SKS</th>
            <th style="width:60px;">Kelas</th>
            <th>Dosen Pengampu</th>
            <th>Jadwal</th>
            <th>Ruangan</th>
        </tr>
    </thead>
    <tbody>
        @foreach($krs->details ?? [] as $i => $d)
        <tr>
            <td style="text-align:center;">{{ $i + 1 }}</td>
            <td style="text-align:center; font-size:10pt;">{{ $d->course?->code }}</td>
            <td>{{ $d->course?->name }}</td>
            <td style="text-align:center;">{{ $d->course?->credits }}</td>
            <td style="text-align:center;">{{ $d->class_?->name ?? '-' }}</td>
            <td style="font-size:10pt;">{{ $d->class_?->lecturer?->display_name ?? $d->class_?->lecturer?->full_name ?? '-' }}</td>
            <td style="font-size:10pt;">
                @if($d->class_?->schedules?->count())
                    {{ $d->class_->schedules[0]->day }} {{ substr($d->class_->schedules[0]->start_time, 0, 5) }}-{{ substr($d->class_->schedules[0]->end_time, 0, 5) }}
                @else - @endif
            </td>
            <td style="font-size:10pt;">{{ $d->class_?->room?->name ?? $d->class_?->schedules?->first()?->room?->name ?? '-' }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" style="text-align:right; font-weight:bold;">Total SKS:</td>
            <td style="text-align:center; font-weight:bold;">{{ $krs->total_credits }}</td>
            <td colspan="4"></td>
        </tr>
    </tfoot>
</table>

{{-- TANDA TANGAN --}}
<div style="margin-top: 30px;">
    <table class="sign-table" style="width:100%;">
        <tr>
            <td>
                <p style="font-size:9pt;">Ditandatangani Tanggal:<br><strong>{{ $krs->signed_by_kaprodi_at?->format('d/m/Y') ?? '......................' }}</strong></p>
                <p><strong>Ketua Program Studi</strong></p>
                <br>
                @if($krs->signed_by_kaprodi_at)
                    <img src="{{ $qrKaprodi }}" alt="QR">
                @else
                    <div style="height:70px;"></div>
                @endif
                <br>
                <span class="sign-name">{{ $kaprodi?->display_name ?? $kaprodi?->full_name ?? '-' }}</span><br>
                <span class="sign-role">NIDN: {{ $kaprodi?->nidn ?? '-' }}</span>
            </td>
            <td>
                <p style="font-size:9pt;">Disetujui Tanggal:<br><strong>{{ $krs->approved_at?->format('d/m/Y') ?? '......................' }}</strong></p>
                <p><strong>Dosen Wali</strong></p>
                <br>
                @if($krs->status === 'APPROVED')
                    <img src="{{ $qrAdvisor }}" alt="QR">
                @else
                    <div style="height:70px;"></div>
                @endif
                <br>
                <span class="sign-name">{{ $krs->advisor?->display_name ?? $krs->advisor?->full_name ?? '-' }}</span><br>
                <span class="sign-role">NIDN: {{ $krs->advisor?->nidn ?? '-' }}</span>
            </td>
            <td>
                <p style="font-size:9pt;">Diajukan Tanggal:<br><strong>{{ $krs->submitted_at?->format('d/m/Y') ?? '......................' }}</strong></p>
                <p><strong>Mahasiswa</strong></p>
                <br>
                @if($krs->status === 'APPROVED')
                    <img src="{{ $qrStudent }}" alt="QR">
                @else
                    <div style="height:70px;"></div>
                @endif
                <br>
                <span class="sign-name">{{ $krs->student?->name ?? '-' }}</span><br>
                <span class="sign-role">NIM: {{ $krs->student?->nim ?? '-' }}</span>
            </td>
        </tr>
    </table>
</div>

<div style="margin-top: 15px; text-align: center; font-size: 9pt; color: #555;">
    Status: {{ $krs->status }}
</div>

{{-- Footer Verifikasi Elektronik --}}
<div style="position: fixed; bottom: 0; left: 0; right: 0; padding: 8px 15mm 0 15mm; border-top: 1px solid #ccc;">
    <table style="width:100%; border:none;">
        <tr>
            <td style="width:60px; border:none; padding:0; vertical-align:middle;">
                <a href="{{ $verifyUrl }}"><img src="{{ $qrFooter }}" width="60" height="60" alt="QR Verifikasi"></a>
            </td>
            <td style="border:none; padding:0 0 0 8px; vertical-align:middle; font-size:10pt; color:#555; line-height:1.3;">
                Dokumen ini telah ditandatangani dan distempel secara elektronik melalui aplikasi Al-Jawami Smart Campus, klik atau scan QR Code untuk verifikasi.
            </td>
        </tr>
    </table>
</div>

</body>
</html>
