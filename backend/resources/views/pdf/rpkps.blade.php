<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>RPKPS - {{ $rpkps->course?->name }}</title>
    <style>
        @page { margin: 10mm 12mm 20mm 12mm; }
        body { font-family: 'Times New Roman', serif; font-size: 11pt; margin: 0; padding: 0; line-height: 1.5; position: relative; }
        table { width: 100%; border-collapse: collapse; }

        /* Semua border seragam 1px solid */
        .bordered td, .bordered th { border: 1px solid #000; padding: 5px 8px; vertical-align: top; }
        .bordered th { font-weight: bold; text-align: center; }

        /* Header */
        .header-table { border: 1px solid #000; }
        .header-table td { padding: 10px; vertical-align: middle; border: none; }
        .header-logo { width: 80px; text-align: center; border-right: 1px solid #000 !important; }
        .header-logo img { max-width: 65px; max-height: 65px; }
        .header-text { font-size: 14pt; font-weight: bold; }

        /* Title row */
        .title-row { border: 1px solid #000; border-top: none; }
        .title-row td { text-align: center; padding: 10px; font-size: 14pt; font-weight: bold; }

        /* Auth box */
        .auth-box { text-align: center; padding: 8px 5px; }
        .auth-box img { width: 50px; height: 50px; margin: 5px 0; }
        .auth-box .name { font-weight: bold; text-decoration: underline; }
        .auth-box .nidn { font-size: 10pt; }

        .page-break { page-break-before: always; }

        /* Weekly table - sedikit lebih kecil karena banyak kolom */
        .weekly-table td, .weekly-table th { border: 1px solid #000; padding: 4px 5px; font-size: 10pt; vertical-align: top; }
        .weekly-table th { text-align: center; font-weight: bold; }

        .ref-list { margin: 0; padding-left: 20px; }
        .ref-list li { margin-bottom: 3px; }

        .sign-table td { padding: 10px 20px; text-align: center; vertical-align: top; border: none; width: 50%; }
        .footer-info { margin-top: 15px; text-align: center; font-size: 9pt; color: #555; }
    </style>
</head>
<body>

{{-- HEADER --}}
<table class="header-table">
    <tr>
        <td class="header-logo">
            @if($logoPath && file_exists($logoPath))
                <img src="{{ $logoPath }}" alt="Logo">
            @endif
        </td>
        <td class="header-text">
            {{ strtoupper($institution?->name ?? 'PERGURUAN TINGGI') }}<br>
            @if($rpkps->course?->studyProgram?->faculty)
                {{ strtoupper($rpkps->course->studyProgram->faculty->name) }}<br>
            @endif
            PROGRAM STUDI {{ strtoupper($rpkps->course?->studyProgram?->name ?? '') }}
        </td>
    </tr>
</table>
<table class="title-row">
    <tr><td>RENCANA PEMBELAJARAN SEMESTER</td></tr>
</table>

{{-- INFO MK --}}
<table class="bordered" style="border-top:none;">
    <tr>
        <th>Nama Mata Kuliah</th>
        <th>Kode</th>
        <th>Rumpun Mata Kuliah</th>
        <th>SKS</th>
        <th>Semester</th>
        <th>Tgl. Penyusunan</th>
    </tr>
    <tr>
        <td>{{ $rpkps->course?->name }}</td>
        <td style="text-align:center;">{{ $rpkps->course?->code }}</td>
        <td style="text-align:center;">{{ $rpkps->course?->studyProgram?->name ?? '-' }}</td>
        <td style="text-align:center;">{{ $rpkps->course?->credits }}</td>
        <td style="text-align:center;">{{ $rpkps->course?->semester ? 'Semester ' . $rpkps->course->semester : '-' }}</td>
        <td style="text-align:center;">{{ $rpkps->created_at?->format('d/m/Y') }}</td>
    </tr>
</table>

{{-- OTORISASI --}}
<table class="bordered" style="border-top:none;">
    <tr>
        <td style="width:15%;"><strong>OTORISASI</strong></td>
        <td style="width:42.5%; text-align:center;"><strong>Dosen Pengembang RPS</strong></td>
        <td style="width:42.5%; text-align:center;"><strong>Ketua Program Studi</strong></td>
    </tr>
    <tr>
        <td></td>
        <td class="auth-box">
            @if(($rpkps->status === 'DISETUJUI' || $rpkps->status === 'DIKUNCI') && $qrDosenUrl)
                <img src="{{ $qrDosenUrl }}">
            @else
                <div style="height:80px;"></div>
            @endif
            <div class="name">{{ $rpkps->lecturer?->display_name ?? $rpkps->lecturer?->full_name ?? '-' }}</div>
            <div class="nidn">NIDN: {{ $rpkps->lecturer?->nidn ?? '-' }}</div>
        </td>
        <td class="auth-box">
            @if(($rpkps->status === 'DISETUJUI' || $rpkps->status === 'DIKUNCI') && $qrKaprodiUrl)
                <img src="{{ $qrKaprodiUrl }}">
            @else
                <div style="height:80px;"></div>
            @endif
            <div class="name">{{ $kaprodi?->display_name ?? $kaprodi?->full_name ?? '-' }}</div>
            <div class="nidn">NIDN: {{ $kaprodi?->nidn ?? '-' }}</div>
        </td>
    </tr>
</table>

{{-- CPL --}}
<table class="bordered" style="border-top:none;">
    <tr>
        <td style="width:22%; vertical-align:top;"><strong>Capaian Pembelajaran (CP)</strong></td>
        <td>
            <strong>Capaian Pembelajaran Lulus Program Studi:</strong><br>
            @forelse($rpkps->cpls ?? [] as $cpl)
                <p style="margin:4px 0;"><strong>{{ $cpl->code }}</strong> &nbsp; {{ $cpl->description }}</p>
            @empty
                <p style="color:#666; font-style:italic;">Belum ada CPL yang dipetakan.</p>
            @endforelse
        </td>
    </tr>
</table>

{{-- CPMK --}}
<table class="bordered" style="border-top:none;">
    <tr>
        <td style="width:22%; vertical-align:top;"></td>
        <td>
            <strong>Capaian Pembelajaran Mata Kuliah:</strong><br>
            @forelse($rpkps->cpmks ?? [] as $cpmk)
                <p style="margin:4px 0;"><strong>{{ $cpmk->code }}</strong> &nbsp; {{ $cpmk->description }}</p>
                @foreach($cpmk->subCpmks ?? [] as $sub)
                    <p style="margin:2px 0 2px 20px; font-size:10pt;">{{ $sub->code }} &nbsp; {{ $sub->description }}</p>
                @endforeach
            @empty
                <p style="color:#666; font-style:italic;">Belum ada CPMK.</p>
            @endforelse
        </td>
    </tr>
</table>

{{-- DESKRIPSI --}}
<table class="bordered" style="border-top:none;">
    <tr>
        <td style="width:22%; vertical-align:top;"><strong>Deskripsi Singkat Matakuliah</strong></td>
        <td>{{ $rpkps->course_description ?? '-' }}</td>
    </tr>
</table>

{{-- BAHAN KAJIAN --}}
<table class="bordered" style="border-top:none;">
    <tr>
        <td style="width:22%; vertical-align:top;"><strong>Bahan Kajian</strong></td>
        <td>
            @if($rpkps->learningMaterials && $rpkps->learningMaterials->count())
                <ol style="margin:0; padding-left:20px;">
                    @foreach($rpkps->learningMaterials as $mat)
                        <li>{{ $mat->topic }}{{ $mat->subtopics ? ': ' . $mat->subtopics : '' }}</li>
                    @endforeach
                </ol>
            @else
                {{ $rpkps->course_scope ?? '-' }}
            @endif
        </td>
    </tr>
</table>

{{-- PUSTAKA --}}
@if($rpkps->references && $rpkps->references->count())
<table class="bordered" style="border-top:none;">
    <tr>
        <td style="width:22%; vertical-align:top;"><strong>Pustaka</strong></td>
        <td>
            @php $utama = $rpkps->references->where('type', 'Utama'); $pendukung = $rpkps->references->where('type', 'Pendukung'); @endphp
            @if($utama->count())
                <strong>Pustaka Utama</strong>
                <ol class="ref-list">
                    @foreach($utama as $ref)
                        <li>{{ $ref->author ? $ref->author . ', ' : '' }}<em>{{ $ref->title }}</em>{{ $ref->publisher ? ', ' . $ref->publisher : '' }}{{ $ref->year ? ', ' . $ref->year : '' }}</li>
                    @endforeach
                </ol>
            @endif
            @if($pendukung->count())
                <br><strong>Pustaka Pendukung</strong>
                <ol class="ref-list">
                    @foreach($pendukung as $ref)
                        <li>{{ $ref->author ? $ref->author . ', ' : '' }}<em>{{ $ref->title }}</em>{{ $ref->publisher ? ', ' . $ref->publisher : '' }}{{ $ref->year ? ', ' . $ref->year : '' }}</li>
                    @endforeach
                </ol>
            @endif
        </td>
    </tr>
</table>
@endif

{{-- PRASYARAT --}}
<table class="bordered" style="border-top:none;">
    <tr>
        <td style="width:22%;"><strong>Mata Kuliah Prasyarat</strong></td>
        <td>{{ $rpkps->prerequisites ?? '-' }}</td>
    </tr>
</table>

{{-- RENCANA MINGGUAN --}}
@if($rpkps->weeklyPlans && $rpkps->weeklyPlans->count())
<div class="page-break"></div>
<table class="weekly-table">
    <thead>
        <tr>
            <th style="width:35px;">Pert.</th>
            <th style="width:18%;">CP-Sub MK<br>(Kemampuan Akhir yang Diharapkan)</th>
            <th style="width:20%;">Bahan Kajian<br>(Materi Ajar)</th>
            <th style="width:13%;">Metode Pembelajaran</th>
            <th style="width:8%;">Alokasi Waktu</th>
            <th style="width:14%;">Pengalaman Belajar</th>
            <th style="width:13%;">Kriteria/Jenis Penilaian</th>
            <th style="width:5%;">Bobot (%)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rpkps->weeklyPlans->sortBy('week_number') as $w)
        <tr>
            <td style="text-align:center;">{{ $w->week_number }}</td>
            <td>{{ $w->sub_cpmk ?? '' }}</td>
            <td>{{ $w->learning_material ?? '' }}</td>
            <td>{{ is_array($w->methods) ? implode(', ', $w->methods) : ($w->methods ?? '') }}</td>
            <td style="text-align:center;">{{ $w->duration ?? '' }}</td>
            <td>{{ $w->student_activity ?? '' }}</td>
            <td>{{ $w->assessment_form ?? '' }}</td>
            <td style="text-align:center;">{{ $w->weight ?? '' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- TANDA TANGAN AKHIR --}}
<div style="margin-top: 40px;">
    <table class="sign-table" style="width:100%;">
        <tr>
            <td>
                <p><strong>Ketua Program Studi</strong></p>
                <br>
                @if(($rpkps->status === 'DISETUJUI' || $rpkps->status === 'DIKUNCI') && $qrKaprodiUrl)
                    <img src="{{ $qrKaprodiUrl }}" style="width:50px; height:50px;">
                @else
                    <div style="height:50px;"></div>
                @endif
                <br><br>
                <strong style="text-decoration:underline;">{{ $kaprodi?->display_name ?? $kaprodi?->full_name ?? '-' }}</strong><br>
                <span style="font-size:10pt;">NIDN: {{ $kaprodi?->nidn ?? '-' }}</span>
            </td>
            <td>
                <p><strong>Dosen Pengampu Mata Kuliah</strong></p>
                <br>
                @if(($rpkps->status === 'DISETUJUI' || $rpkps->status === 'DIKUNCI') && $qrDosenUrl)
                    <img src="{{ $qrDosenUrl }}" style="width:50px; height:50px;">
                @else
                    <div style="height:50px;"></div>
                @endif
                <br><br>
                <strong style="text-decoration:underline;">{{ $rpkps->lecturer?->display_name ?? $rpkps->lecturer?->full_name ?? '-' }}</strong><br>
                <span style="font-size:10pt;">NIDN: {{ $rpkps->lecturer?->nidn ?? '-' }}</span>
            </td>
        </tr>
    </table>
</div>

<div class="footer-info">
    Kode: {{ $rpkps->code }}
</div>

{{-- Footer Verifikasi Elektronik (fixed di bawah halaman) --}}
@if($rpkps->verification_code)
<div style="position: fixed; bottom: 0; left: 0; right: 0; padding-top: 10px; border-top: 1px solid #ccc;">
    <table style="width:100%; border:none;">
        <tr>
            <td style="width:60px; border:none; padding:0; vertical-align:middle;">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=60x60&data={{ urlencode($verifyUrl) }}" width="60" height="60" alt="QR Verifikasi">
            </td>
            <td style="border:none; padding:0 0 0 8px; vertical-align:middle; font-size:8px; color:#666; line-height:1.4;">
                Dokumen ini telah ditandatangani dan distempel secara elektronik melalui aplikasi Al-Jawami Smart Campus, scan QR Code untuk verifikasi.
            </td>
        </tr>
    </table>
</div>
@endif

</body>
</html>
