<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
@page { size: A4 portrait; margin: 15mm 10mm; }
body { font-family: Arial, sans-serif; font-size: 11pt; color: #1f2937; }
.header { text-align: center; margin-bottom: 20px; }
.header h1 { font-size: 16pt; margin: 0 0 4px; color: #1e3a8a; }
.header p { font-size: 10pt; color: #6b7280; margin: 2px 0; }
table { width: 100%; border-collapse: collapse; margin-top: 12px; }
th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; font-size: 10pt; }
th { background: #1e3a8a; color: #fff; font-weight: bold; text-align: center; }
tr:nth-child(even) { background: #f9fafb; }
td.center { text-align: center; }
.footer { margin-top: 20px; font-size: 9pt; color: #6b7280; }
.total { margin-top: 12px; font-weight: bold; font-size: 11pt; }
</style>
</head>
<body>
<div class="header">
    <h1>DAFTAR HADIR</h1>
    <h2 style="font-size:13pt; margin:4px 0;">{{ $event->title }}</h2>
    <p>Tanggal: {{ $event->event_date?->format('d F Y') }}
        @if($event->start_time) | Waktu: {{ $event->start_time }} - {{ $event->end_time ?? 'selesai' }} @endif
    </p>
    <p>Tempat: {{ $event->location ?? '-' }} | Penyelenggara: {{ $event->organizer ?? '-' }}</p>
    <p>Kategori: {{ $event->category }} | Jenis: {{ $event->type }}</p>
</div>

<table>
    <thead>
        <tr>
            <th style="width:30px">No</th>
            <th>Nama</th>
            <th>Instansi / Jabatan</th>
            <th>No. HP</th>
            <th>Metode</th>
            <th>Waktu Hadir</th>
        </tr>
    </thead>
    <tbody>
        @forelse($attendances as $i => $att)
        <tr>
            <td class="center">{{ $i + 1 }}</td>
            <td>{{ $att->user?->name ?? $att->guest_name ?? '-' }}</td>
            <td>{{ $att->guest_institution ?? $att->guest_position ?? '-' }}</td>
            <td>{{ $att->guest_phone ?? '-' }}</td>
            <td class="center">{{ $att->method === 'APP' ? 'Aplikasi' : ($att->method === 'FORM' ? 'Form' : $att->method) }}</td>
            <td class="center">{{ $att->attended_at?->format('d/m/Y H:i') ?? '-' }}</td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center; color:#9ca3af;">Belum ada peserta yang hadir</td></tr>
        @endforelse
    </tbody>
</table>

<p class="total">Total Hadir: {{ $attendances->count() }} orang</p>

<div class="footer">
    <p>Dicetak dari Al-Jawami Smart Campus pada {{ now()->format('d F Y H:i') }}</p>
</div>
</body>
</html>
