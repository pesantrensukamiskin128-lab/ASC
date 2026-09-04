<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Transkrip - {{ $student->nim }}</title>
    <style>
        @page { margin: 12mm 13mm 17mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 8.5pt; line-height: 1.3; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: middle; }
        .logo { width: 68px; text-align: center; }
        .logo img { max-width: 55px; max-height: 55px; }
        .institution { text-align: center; font-family: DejaVu Serif, serif; }
        .institution strong { font-size: 14pt; }
        .institution div { font-size: 8pt; }
        .rule { border-top: 2px solid #111; border-bottom: 1px solid #111; height: 2px; margin: 6px 0 8px; }
        h1 { text-align: center; font-size: 13pt; margin: 0 0 10px; letter-spacing: .4px; }
        .identity td { padding: 2px 4px; vertical-align: top; }
        .identity .label { width: 92px; font-weight: bold; }
        .grades { margin-top: 10px; }
        .grades th, .grades td { border: 1px solid #374151; padding: 4px 5px; }
        .grades th { background: #e5e7eb; text-align: center; font-size: 8pt; }
        .center { text-align: center; }
        .summary { margin-top: 8px; width: 43%; margin-left: auto; page-break-inside: avoid; }
        .summary td { border: 1px solid #6b7280; padding: 4px 6px; }
        .summary .value { text-align: right; font-weight: bold; }
        .note { margin-top: 8px; font-size: 7.5pt; color: #4b5563; }
        .signature { margin-top: 22px; page-break-inside: avoid; }
        .signature td { width: 50%; text-align: center; vertical-align: top; }
        .signature-space { height: 50px; }
        .name { font-weight: bold; text-decoration: underline; }
        .footer { position: fixed; left: 0; right: 0; bottom: -7mm; color: #6b7280; font-size: 7pt; border-top: 1px solid #d1d5db; padding-top: 3px; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    @if($letterheadPath && file_exists($letterheadPath))
        <div style="text-align:center"><img src="{{ $letterheadPath }}" style="width:100%; max-height:90px; object-fit:contain" alt="Kop institusi"></div>
    @else
        <table class="header"><tr><td class="logo">@if($logoPath && file_exists($logoPath))<img src="{{ $logoPath }}" alt="Logo">@endif</td><td class="institution"><strong>{{ strtoupper($institution?->name ?? 'PERGURUAN TINGGI') }}</strong><div>{{ $institution?->address }}</div><div>{{ collect([$institution?->phone, $institution?->email, $institution?->website])->filter()->join(' | ') }}</div></td><td style="width:68px"></td></tr></table>
        <div class="rule"></div>
    @endif

    <h1>TRANSKRIP NILAI AKADEMIK</h1>
    <table class="identity">
        <tr><td class="label">NIM</td><td>: {{ $student->nim }}</td><td class="label">Program Studi</td><td>: {{ $student->studyProgram?->name ?? '-' }}</td></tr>
        <tr><td class="label">Nama</td><td>: {{ $student->name }}</td><td class="label">Jenjang</td><td>: {{ $student->studyProgram?->level ?? '-' }}</td></tr>
        <tr><td class="label">Fakultas</td><td>: {{ $student->studyProgram?->faculty?->name ?? '-' }}</td><td class="label">Status</td><td>: {{ $student->status }}</td></tr>
    </table>

    <table class="grades">
        <thead><tr><th style="width:25px">No</th><th style="width:68px">Kode MK</th><th>Mata Kuliah</th><th style="width:102px">Semester</th><th style="width:30px">SKS</th><th style="width:34px">Nilai</th><th style="width:38px">Bobot</th><th style="width:42px">Mutu</th></tr></thead>
        <tbody>
        @forelse($grades as $index => $grade)
            @php($credits = (int) ($grade->course?->credits ?? 0))
            <tr><td class="center">{{ $index + 1 }}</td><td class="center">{{ $grade->course?->code }}</td><td>{{ $grade->course?->name }}</td><td>{{ $grade->semester?->name ?? '-' }}</td><td class="center">{{ $credits }}</td><td class="center"><strong>{{ $grade->letter_grade ?? '-' }}</strong></td><td class="center">{{ $grade->grade_point !== null ? number_format((float) $grade->grade_point, 2, ',', '.') : '-' }}</td><td class="center">{{ $grade->grade_point !== null ? number_format($credits * (float) $grade->grade_point, 2, ',', '.') : '-' }}</td></tr>
        @empty
            <tr><td colspan="8" class="center">Belum ada data transkrip.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table class="summary">
        <tr><td>Total SKS bernilai</td><td class="value">{{ $totalCredits }}</td></tr>
        <tr><td>Indeks Prestasi Kumulatif</td><td class="value">{{ number_format((float) $ipk, 2, ',', '.') }}</td></tr>
    </table>
    <div class="note">Catatan: Mutu = SKS x bobot nilai. Dokumen ini menampilkan seluruh nilai yang tercatat pada ASC.</div>

    <table class="signature"><tr>
        <td></td>
        <td>Ketua Program Studi,<div class="signature-space"></div><span class="name">{{ $student->studyProgram?->headLecturer?->display_name ?? '................................' }}</span><br>NIDN {{ $student->studyProgram?->headLecturer?->nidn ?? '-' }}</td>
    </tr></table>

    <div class="footer">Dicetak dari Al-Jawami Smart Campus pada {{ $printedAt->format('d/m/Y H:i') }}</div>
</body>
</html>
