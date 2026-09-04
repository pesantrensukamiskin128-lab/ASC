<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>KHS - {{ $student->nim }}</title>
    <style>
        @page { margin: 13mm 15mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 9.5pt; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: middle; }
        .logo { width: 70px; text-align: center; }
        .logo img { max-width: 58px; max-height: 58px; }
        .institution { text-align: center; font-family: DejaVu Serif, serif; }
        .institution strong { font-size: 14pt; }
        .institution div { font-size: 8.5pt; }
        .rule { border-top: 2px solid #111; border-bottom: 1px solid #111; height: 2px; margin: 7px 0 10px; }
        h1 { text-align: center; font-size: 13pt; margin: 0 0 12px; letter-spacing: .4px; }
        .identity td { padding: 2px 4px; vertical-align: top; }
        .identity .label { width: 95px; font-weight: bold; }
        .grades { margin-top: 12px; }
        .grades th, .grades td { border: 1px solid #374151; padding: 5px 6px; }
        .grades th { background: #e5e7eb; text-align: center; font-size: 8.5pt; }
        .center { text-align: center; }
        .right { text-align: right; }
        .summary { margin-top: 9px; width: 48%; margin-left: auto; }
        .summary td { border: 1px solid #6b7280; padding: 4px 6px; }
        .summary .value { text-align: right; font-weight: bold; }
        .signature { margin-top: 24px; page-break-inside: avoid; }
        .signature td { width: 50%; text-align: center; vertical-align: top; }
        .signature-space { height: 52px; }
        .name { font-weight: bold; text-decoration: underline; }
        .footer { position: fixed; left: 0; right: 0; bottom: -8mm; text-align: center; color: #6b7280; font-size: 7.5pt; border-top: 1px solid #d1d5db; padding-top: 4px; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    @if($letterheadPath && file_exists($letterheadPath))
        <div style="text-align:center"><img src="{{ $letterheadPath }}" style="width:100%; max-height:92px; object-fit:contain" alt="Kop institusi"></div>
    @else
        <table class="header"><tr>
            <td class="logo">@if($logoPath && file_exists($logoPath))<img src="{{ $logoPath }}" alt="Logo">@endif</td>
            <td class="institution">
                <strong>{{ strtoupper($institution?->name ?? 'PERGURUAN TINGGI') }}</strong>
                <div>{{ $institution?->address }}</div>
                <div>{{ collect([$institution?->phone, $institution?->email, $institution?->website])->filter()->join(' | ') }}</div>
            </td><td style="width:70px"></td>
        </tr></table>
        <div class="rule"></div>
    @endif

    <h1>KARTU HASIL STUDI (KHS)</h1>
    <table class="identity">
        <tr><td class="label">NIM</td><td>: {{ $student->nim }}</td><td class="label">Semester</td><td>: {{ $semester->name }}</td></tr>
        <tr><td class="label">Nama</td><td>: {{ $student->name }}</td><td class="label">Status</td><td>: {{ $summary?->status ?? $student->status }}</td></tr>
        <tr><td class="label">Program Studi</td><td>: {{ $student->studyProgram?->name ?? '-' }}</td><td class="label">Dosen Wali</td><td>: {{ $student->advisor?->display_name ?? '-' }}</td></tr>
        <tr><td class="label">Fakultas</td><td>: {{ $student->studyProgram?->faculty?->name ?? '-' }}</td><td class="label">Batas SKS</td><td>: {{ $summary?->credit_limit ?? '-' }}</td></tr>
    </table>

    <table class="grades">
        <thead><tr><th style="width:28px">No</th><th style="width:75px">Kode MK</th><th>Mata Kuliah</th><th style="width:34px">SKS</th><th style="width:44px">Angka</th><th style="width:42px">Huruf</th><th style="width:42px">Bobot</th><th style="width:48px">Mutu</th></tr></thead>
        <tbody>
        @forelse($grades as $index => $grade)
            @php($credits = (int) ($grade->course?->credits ?? 0))
            <tr><td class="center">{{ $index + 1 }}</td><td class="center">{{ $grade->course?->code }}</td><td>{{ $grade->course?->name }}</td><td class="center">{{ $credits }}</td><td class="center">{{ $grade->final_score !== null ? number_format((float) $grade->final_score, 2, ',', '.') : '-' }}</td><td class="center"><strong>{{ $grade->letter_grade ?? '-' }}</strong></td><td class="center">{{ $grade->grade_point !== null ? number_format((float) $grade->grade_point, 2, ',', '.') : '-' }}</td><td class="center">{{ $grade->grade_point !== null ? number_format($credits * (float) $grade->grade_point, 2, ',', '.') : '-' }}</td></tr>
        @empty
            <tr><td colspan="8" class="center">Belum ada nilai pada semester ini.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table class="summary">
        <tr><td>SKS diambil</td><td class="value">{{ $summary?->credits_taken ?? $totalCredits }}</td></tr>
        <tr><td>SKS bernilai</td><td class="value">{{ $totalCredits }}</td></tr>
        <tr><td>Indeks Prestasi (IP)</td><td class="value">{{ number_format((float) $ips, 2, ',', '.') }}</td></tr>
        <tr><td>IP Kumulatif (IPK)</td><td class="value">{{ $summary?->cumulative_gpa !== null ? number_format((float) $summary->cumulative_gpa, 2, ',', '.') : '-' }}</td></tr>
    </table>

    <table class="signature"><tr>
        <td>Mahasiswa,<div class="signature-space"></div><span class="name">{{ $student->name }}</span><br>NIM {{ $student->nim }}</td>
        <td>Ketua Program Studi,<div class="signature-space"></div><span class="name">{{ $student->studyProgram?->headLecturer?->display_name ?? '................................' }}</span><br>NIDN {{ $student->studyProgram?->headLecturer?->nidn ?? '-' }}</td>
    </tr></table>

    <div class="footer">Dicetak dari Al-Jawami Smart Campus pada {{ $printedAt->format('d/m/Y H:i') }}</div>
</body>
</html>
