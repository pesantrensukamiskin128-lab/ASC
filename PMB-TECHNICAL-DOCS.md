# Dokumentasi Teknis Modul PMB

## Arsitektur Sistem

### Backend (Laravel)

#### Models
```
app/Models/
├── PmbPeriod.php          - Periode/gelombang pendaftaran
├── PmbPath.php            - Jalur seleksi (reguler, prestasi, khusus)
├── PmbExamType.php        - Jenis ujian seleksi
├── Applicant.php          - Data pendaftar utama
├── ApplicantChoice.php    - Pilihan program studi pendaftar
├── ApplicantEducation.php - Riwayat pendidikan pendaftar
├── ApplicantFamily.php    - Data keluarga pendaftar
├── ApplicantDocument.php  - Dokumen pendaftar
├── PmbExamScore.php       - Nilai ujian per jenis
├── PmbSelectionResult.php - Hasil perhitungan seleksi
└── PmbReRegistration.php  - Data daftar ulang
```

#### Controllers
```
app/Http/Controllers/Api/
├── PmbPeriodController.php      - CRUD periode
├── PmbPathController.php        - CRUD jalur
├── PmbExamTypeController.php    - CRUD jenis ujian
├── PmbRegistrantController.php  - CRUD & workflow pendaftar (admin)
└── PmbPublicController.php      - API untuk calon mahasiswa
```

#### Endpoints

##### Admin Endpoints (Internal)
```php
// Periode
GET    /api/pmb-periods              - List periode (paginated)
POST   /api/pmb-periods              - Create periode
GET    /api/pmb-periods/{id}         - Detail periode
PUT    /api/pmb-periods/{id}         - Update periode
DELETE /api/pmb-periods/{id}         - Delete periode
GET    /api/pmb-periods-all          - All periods (no pagination)

// Jalur
GET    /api/pmb-paths                - List jalur
POST   /api/pmb-paths                - Create jalur
PUT    /api/pmb-paths/{id}           - Update jalur
DELETE /api/pmb-paths/{id}           - Delete jalur

// Jenis Ujian
GET    /api/pmb-exam-types           - List jenis ujian
POST   /api/pmb-exam-types           - Create jenis ujian
PUT    /api/pmb-exam-types/{id}      - Update jenis ujian
DELETE /api/pmb-exam-types/{id}      - Delete jenis ujian

// Pendaftar
GET    /api/pmb-registrants          - List pendaftar (search, filter, paginate)
GET    /api/pmb-registrants/{id}     - Detail pendaftar lengkap
DELETE /api/pmb-registrants/{id}     - Delete pendaftar
GET    /api/pmb-registrants/statistics - Statistik dashboard

// Workflow
POST   /api/pmb-registrants/{id}/verify         - Verifikasi berkas
POST   /api/pmb-registrants/{id}/set-selection  - Set mengikuti seleksi
POST   /api/pmb-registrants/{id}/scores         - Input nilai seleksi
POST   /api/pmb-registrants/{id}/calculate      - Hitung nilai akhir
POST   /api/pmb-registrants/{id}/final-status   - Set status akhir (lulus/tidak lulus)
POST   /api/pmb-registrants/{id}/re-registration - Proses daftar ulang (buat mahasiswa)
```

##### Public Endpoints (Calon Mahasiswa)
```php
POST   /api/pmb/register             - Registrasi akun baru
GET    /api/pmb/active-period        - Get periode aktif
GET    /api/pmb/paths                - List jalur aktif
GET    /api/pmb/programs             - List program studi
GET    /api/pmb/my/registration      - Data pendaftaran user login
POST   /api/pmb/my/form              - Save/update formulir
POST   /api/pmb/my/submit            - Submit formulir
POST   /api/pmb/my/photo             - Upload pas foto
POST   /api/pmb/my/payment           - Konfirmasi pembayaran
GET    /api/pmb/my/card-pdf          - Download kartu peserta PDF
```

---

### Frontend (Vue 3 + TypeScript)

#### Struktur File
```
src/views/pmb/
├── PmbDashboardView.vue           - Dashboard statistik (admin)
├── PmbPeriodView.vue              - CRUD periode (admin)
├── PmbPathView.vue                - CRUD jalur (admin)
├── PmbExamTypeView.vue            - CRUD jenis ujian (admin)
├── PmbRegistrantListView.vue      - List pendaftar (admin)
├── PmbRegistrantDetailView.vue    - Detail & workflow pendaftar (admin)
└── public/
    ├── PmbLandingView.vue         - Landing page (public)
    ├── PmbRegisterView.vue        - Registrasi akun (public)
    ├── PmbLoginView.vue           - Login (public)
    ├── PmbFormView.vue            - Formulir pendaftaran (public)
    └── PmbStatusView.vue          - Status pendaftaran (public)
```

#### Routes
```typescript
// Admin routes (requires auth + permission 'pmb.view')
{
  path: '/pmb',
  name: 'pmb-dashboard',
  component: PmbDashboardView,
  meta: { permission: 'pmb.view' }
}
{
  path: '/pmb/periods',
  name: 'pmb-periods',
  component: PmbPeriodView,
  meta: { permission: 'pmb.view' }
}
// ... (paths, exam-types, registrants)

// Public routes (PmbLayout - no internal auth)
{
  path: '/pmb',
  component: PmbLayout,
  children: [
    { path: '', name: 'pmb-landing', component: PmbLandingView },
    { path: 'register', name: 'pmb-register', component: PmbRegisterView },
    { path: 'login', name: 'pmb-login', component: PmbLoginView },
    { 
      path: 'form', 
      name: 'pmb-form', 
      component: PmbFormView,
      meta: { pmbAuth: true }  // Harus login PMB
    },
    { 
      path: 'status', 
      name: 'pmb-status', 
      component: PmbStatusView,
      meta: { pmbAuth: true }
    },
  ]
}
```

---

## Database Schema

### Tabel Utama

#### `pmb_periods`
```sql
id                      BIGINT PRIMARY KEY
academic_year_id        BIGINT FK
name                    VARCHAR(100)
registration_start      DATE
registration_end        DATE
selection_date          DATE
announcement_date       DATE
re_registration_start   DATE
re_registration_end     DATE
quota                   INT
registration_fee        DECIMAL(15,2)
is_active               BOOLEAN
```

#### `pmb_paths`
```sql
id                      BIGINT PRIMARY KEY
code                    VARCHAR(20) UNIQUE
name                    VARCHAR(100)
description             TEXT
requirements            TEXT
is_active               BOOLEAN
```

#### `pmb_exam_types`
```sql
id                      BIGINT PRIMARY KEY
code                    VARCHAR(20) UNIQUE
name                    VARCHAR(100)
weight                  DECIMAL(5,2)      -- Bobot dalam persen
passing_grade           DECIMAL(5,2)      -- KKM
is_active               BOOLEAN
```

#### `applicants` (Pendaftar)
```sql
id                      BIGINT PRIMARY KEY
pmb_period_id           BIGINT FK
pmb_path_id             BIGINT FK (nullable)
registration_number     VARCHAR UNIQUE     -- Auto-generated
user_id                 BIGINT FK          -- Link ke users table

-- Data Pribadi
full_name               VARCHAR(255)
gender                  ENUM('L','P')
birth_place             VARCHAR(100)
birth_date              DATE
religion                VARCHAR(50)
nik                     VARCHAR(16)
phone                   VARCHAR(20)
email                   VARCHAR(255)
address                 TEXT
province                VARCHAR(100)
city                    VARCHAR(100)
district                VARCHAR(100)
village                 VARCHAR(100)
postal_code             VARCHAR(10)

-- Orang Tua
father_name             VARCHAR(255)
father_occupation       VARCHAR(100)
father_phone            VARCHAR(20)
mother_name             VARCHAR(255)
mother_occupation       VARCHAR(100)
mother_phone            VARCHAR(20)
guardian_name           VARCHAR(255) NULLABLE
guardian_occupation     VARCHAR(100) NULLABLE
guardian_phone          VARCHAR(20) NULLABLE

-- Pendidikan
school_name             VARCHAR(255)
school_address          TEXT
graduation_year         INT
diploma_number          VARCHAR(100)

-- Pilihan Prodi
choice_1                BIGINT FK study_programs
choice_2                BIGINT FK study_programs (nullable)
choice_3                BIGINT FK study_programs (nullable)

-- Prestasi (untuk jalur prestasi)
achievement_description TEXT NULLABLE

-- Dokumen
photo_path              VARCHAR(255) NULLABLE
diploma_link            VARCHAR(500) NULLABLE
family_card_link        VARCHAR(500) NULLABLE
identity_link           VARCHAR(500) NULLABLE

-- Pembayaran
is_paid                 BOOLEAN DEFAULT FALSE
payment_proof           VARCHAR(500) NULLABLE
paid_at                 TIMESTAMP NULLABLE
verified_at             TIMESTAMP NULLABLE

-- Status
status                  ENUM(
                          'DRAFT',
                          'SUBMITTED',
                          'MENUNGGU_VERIFIKASI',
                          'TERVERIFIKASI',
                          'MENGIKUTI_SELEKSI',
                          'LULUS',
                          'TIDAK_LULUS',
                          'DAFTAR_ULANG',
                          'MAHASISWA_BARU'
                        )
submitted_at            TIMESTAMP NULLABLE
```

#### `pmb_exam_scores`
```sql
id                      BIGINT PRIMARY KEY
applicant_id            BIGINT FK
exam_type_id            BIGINT FK
score                   DECIMAL(5,2)       -- Nilai 0-100
note                    TEXT NULLABLE
created_at              TIMESTAMP
```

#### `pmb_selection_results`
```sql
id                      BIGINT PRIMARY KEY
applicant_id            BIGINT FK UNIQUE
final_score             DECIMAL(5,2)       -- Nilai akhir (bobot diterapkan)
recommendation          ENUM('LULUS', 'CADANGAN', 'TIDAK_LULUS')
final_status            ENUM('LULUS', 'TIDAK_LULUS') NULLABLE
accepted_program_id     BIGINT FK study_programs NULLABLE
created_at              TIMESTAMP
```

#### `pmb_re_registrations`
```sql
id                      BIGINT PRIMARY KEY
applicant_id            BIGINT FK UNIQUE
student_id              BIGINT FK students
nim                     VARCHAR(20) UNIQUE
is_completed            BOOLEAN DEFAULT FALSE
completed_at            TIMESTAMP NULLABLE
```

---

## Business Logic

### 1. Status Flow Pendaftar

```
DRAFT
  ↓ (user submit formulir)
SUBMITTED
  ↓ (user konfirmasi bayar)
MENUNGGU_VERIFIKASI
  ↓ (admin verifikasi)
TERVERIFIKASI
  ↓ (admin set seleksi)
MENGIKUTI_SELEKSI
  ↓ (admin input nilai & hitung)
  ├─→ LULUS (jika memenuhi syarat)
  └─→ TIDAK_LULUS (jika tidak memenuhi)
      
LULUS
  ↓ (admin proses daftar ulang)
MAHASISWA_BARU
```

### 2. Perhitungan Nilai Akhir

**Formula**:
```
Nilai Akhir = Σ (Nilai Ujian × Bobot Ujian)
```

**Contoh**:
```php
// Exam types:
TPA       - bobot 40%, passing_grade 60
Tes Bahasa- bobot 30%, passing_grade 55
Interview - bobot 30%, passing_grade 65

// Nilai:
TPA       = 75
Tes Bahasa= 70
Interview = 80

// Perhitungan:
final_score = (75 × 0.4) + (70 × 0.3) + (80 × 0.3)
            = 30 + 21 + 24
            = 75

// Rekomendasi:
if (final_score >= 75)       recommendation = 'LULUS'
else if (final_score >= 60)  recommendation = 'CADANGAN'
else                         recommendation = 'TIDAK_LULUS'

// Cek per komponen:
if (any score < passing_grade)
    warning = 'Ada nilai di bawah KKM'
```

**Backend Implementation** (`PmbRegistrantController@calculate`):
```php
public function calculate(Request $request, $id)
{
    $applicant = Applicant::with(['examScores.examType'])->findOrFail($id);
    
    $finalScore = 0;
    foreach ($applicant->examScores as $score) {
        $finalScore += $score->score * ($score->examType->weight / 100);
    }
    
    // Determine recommendation
    if ($finalScore >= 75) $rec = 'LULUS';
    elseif ($finalScore >= 60) $rec = 'CADANGAN';
    else $rec = 'TIDAK_LULUS';
    
    // Save result
    PmbSelectionResult::updateOrCreate(
        ['applicant_id' => $applicant->id],
        [
            'final_score' => $finalScore,
            'recommendation' => $rec,
        ]
    );
    
    return response()->json([
        'final_score' => $finalScore,
        'recommendation' => $rec,
    ]);
}
```

### 3. Auto-generated Registration Number

**Format**: `PMB-{YEAR}-{PERIOD_CODE}-{SEQUENCE}`
**Contoh**: `PMB-2026-G1-0001`

```php
// Backend
$period = PmbPeriod::find($request->pmb_period_id);
$year = date('Y');
$periodCode = 'G' . $period->id;
$lastSeq = Applicant::where('pmb_period_id', $period->id)
    ->orderBy('id', 'desc')
    ->first();
$sequence = $lastSeq ? intval(substr($lastSeq->registration_number, -4)) + 1 : 1;
$registrationNumber = sprintf('PMB-%s-%s-%04d', $year, $periodCode, $sequence);
```

### 4. Photo Upload

**Backend**:
```php
public function uploadPhoto(Request $request)
{
    $request->validate([
        'photo' => 'required|image|mimes:jpeg,png|max:2048', // 2MB
    ]);
    
    $applicant = Applicant::where('user_id', auth()->id())->firstOrFail();
    
    // Delete old photo
    if ($applicant->photo_path) {
        Storage::delete($applicant->photo_path);
    }
    
    // Store new photo
    $path = $request->file('photo')->store('pmb/photos', 'public');
    $applicant->update(['photo_path' => $path]);
    
    return response()->json([
        'message' => 'Foto berhasil diupload.',
        'photo_url' => asset('storage/' . $path),
    ]);
}
```

**Frontend** (Vue):
```typescript
async function onPhotoSelected(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  
  const formData = new FormData()
  formData.append('photo', file)
  
  uploadingPhoto.value = true
  try {
    const { data } = await api.post('/pmb/my/photo', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    photoPreviewUrl.value = data.photo_url
    toast.success(data.message)
  } catch {
    toast.error('Gagal upload foto.')
  } finally {
    uploadingPhoto.value = false
  }
}
```

### 5. Generate Kartu Peserta PDF

**Backend** (menggunakan DomPDF):
```php
use Barryvdh\DomPDF\Facade\Pdf;

public function downloadCard()
{
    $applicant = Applicant::with([
        'period', 'path', 'studyProgramChoice1'
    ])->where('user_id', auth()->id())->firstOrFail();
    
    if (!in_array($applicant->status, ['TERVERIFIKASI', 'MENGIKUTI_SELEKSI', 'LULUS'])) {
        return response()->json(['message' => 'Kartu belum tersedia.'], 422);
    }
    
    $pdf = Pdf::loadView('pdf.pmb-card', ['applicant' => $applicant]);
    
    return $pdf->download("kartu-peserta-{$applicant->registration_number}.pdf");
}
```

**Template PDF** (`resources/views/pdf/pmb-card.blade.php`):
```html
<!DOCTYPE html>
<html>
<head>
    <title>Kartu Peserta</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .photo { width: 100px; height: 133px; border: 1px solid #000; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>KARTU PESERTA UJIAN SELEKSI</h2>
        <h3>{{ $applicant->period->name }}</h3>
    </div>
    
    <table>
        <tr>
            <td rowspan="6" style="width: 120px; text-align: center;">
                @if($applicant->photo_path)
                    <img src="{{ public_path('storage/' . $applicant->photo_path) }}" class="photo">
                @else
                    <div class="photo"></div>
                @endif
            </td>
            <td width="150">No. Pendaftaran</td>
            <td><strong>{{ $applicant->registration_number }}</strong></td>
        </tr>
        <tr>
            <td>Nama</td>
            <td><strong>{{ $applicant->full_name }}</strong></td>
        </tr>
        <tr>
            <td>Program Studi</td>
            <td>{{ $applicant->studyProgramChoice1->name ?? '-' }}</td>
        </tr>
        <tr>
            <td>Jalur</td>
            <td>{{ $applicant->path->name ?? 'Reguler' }}</td>
        </tr>
        <tr>
            <td>Tempat/Tanggal Lahir</td>
            <td>{{ $applicant->birth_place }}, {{ $applicant->birth_date }}</td>
        </tr>
        <tr>
            <td>Sekolah Asal</td>
            <td>{{ $applicant->school_name }}</td>
        </tr>
    </table>
    
    <div style="margin-top: 30px;">
        <p><strong>Jadwal Seleksi:</strong></p>
        <p>Tanggal: {{ $applicant->period->selection_date }}</p>
        <p>Tempat: [Kampus/Ruangan]</p>
    </div>
    
    <div style="margin-top: 30px; text-align: center;">
        <p><em>Kartu ini wajib dibawa pada saat mengikuti ujian seleksi</em></p>
    </div>
</body>
</html>
```

---

## Security & Permissions

### Admin (Internal)
```php
// Route middleware
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/pmb-registrants', [PmbRegistrantController::class, 'index'])
        ->middleware('permission:pmb.view');
    // ... other admin routes
});
```

### Public (Calon Mahasiswa)
```php
// Menggunakan auth:sanctum juga, tapi user biasa (role PENDAFTAR atau similar)
Route::middleware(['auth:sanctum'])->prefix('pmb/my')->group(function () {
    Route::get('/registration', [PmbPublicController::class, 'myRegistration']);
    Route::post('/form', [PmbPublicController::class, 'saveForm']);
    // ...
});
```

**Authorization Check** (di Controller):
```php
public function myRegistration()
{
    $user = auth()->user();
    $applicant = Applicant::where('user_id', $user->id)->first();
    return response()->json($applicant);
}
```

---

## Testing

### Manual Testing Checklist

#### Admin Flow
- [ ] Create periode (active & inactive)
- [ ] Create jalur (multiple)
- [ ] Create jenis ujian (total bobot = 100%)
- [ ] View dashboard (statistics)
- [ ] Filter pendaftar by periode, status, search
- [ ] Verify pendaftar
- [ ] Set selection
- [ ] Input nilai (all exam types)
- [ ] Calculate final score
- [ ] Set final status (lulus/tidak lulus)
- [ ] Process re-registration (create student)

#### Public Flow
- [ ] Register account
- [ ] Login
- [ ] Fill form (all 6 steps)
- [ ] Save draft
- [ ] Submit form
- [ ] Upload photo
- [ ] Confirm payment
- [ ] View status
- [ ] Download kartu peserta PDF

### Unit Test Example (PHPUnit)
```php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{Applicant, PmbPeriod, PmbExamType, User};

class PmbCalculateTest extends TestCase
{
    public function test_calculate_final_score()
    {
        $user = User::factory()->create();
        $period = PmbPeriod::factory()->create();
        
        // Create exam types
        $tpa = PmbExamType::factory()->create([
            'code' => 'TPA',
            'weight' => 40,
            'passing_grade' => 60,
        ]);
        $tbi = PmbExamType::factory()->create([
            'code' => 'TBI',
            'weight' => 30,
            'passing_grade' => 55,
        ]);
        $int = PmbExamType::factory()->create([
            'code' => 'INT',
            'weight' => 30,
            'passing_grade' => 65,
        ]);
        
        // Create applicant
        $applicant = Applicant::factory()->create([
            'user_id' => $user->id,
            'pmb_period_id' => $period->id,
            'status' => 'MENGIKUTI_SELEKSI',
        ]);
        
        // Input scores
        $applicant->examScores()->createMany([
            ['exam_type_id' => $tpa->id, 'score' => 75],
            ['exam_type_id' => $tbi->id, 'score' => 70],
            ['exam_type_id' => $int->id, 'score' => 80],
        ]);
        
        // Calculate
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/pmb-registrants/{$applicant->id}/calculate");
        
        $response->assertOk();
        $response->assertJson([
            'final_score' => 75.0,
            'recommendation' => 'LULUS',
        ]);
    }
}
```

---

## Deployment Notes

### Environment Variables
```env
# .env
FILESYSTEM_DISK=public     # For photo storage
PMB_REGISTRATION_FEE=250000
PMB_PASSING_GRADE=65
```

### Storage Setup
```bash
# Link storage
php artisan storage:link

# Set permissions
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
```

### Database Migration
```bash
php artisan migrate
php artisan db:seed --class=PermissionSeeder  # Add PMB permissions
```

### Frontend Build
```bash
cd frontend
npm install
npm run build
```

---

## Future Enhancements

1. **Email Notifications**
   - Kirim email otomatis saat status berubah
   - Template email untuk welcome, verifikasi, pengumuman

2. **Payment Gateway Integration**
   - Midtrans, Xendit, dll
   - Virtual account otomatis
   - Webhook callback

3. **Bulk Operations**
   - Bulk verify
   - Bulk import pendaftar
   - Bulk export to Excel

4. **Advanced Analytics**
   - Chart pendaftar per hari
   - Conversion funnel
   - Demografi analysis

5. **Interview Scheduling**
   - Slot booking untuk interview
   - Calendar integration

6. **Document Management**
   - Direct upload (tidak via link Google Drive)
   - PDF viewer in-app
   - Document versioning

7. **SMS/WhatsApp Notification**
   - Integrasi Twilio/Fonnte
   - Notifikasi real-time

8. **Multi-language Support**
   - i18n untuk bahasa Indonesia & Inggris

---

## Troubleshooting

### Common Issues

#### 1. "Kartu peserta belum tersedia"
**Cause**: Status belum TERVERIFIKASI
**Solution**: Admin harus verifikasi dulu

#### 2. "Total bobot tidak 100%"
**Cause**: Bobot jenis ujian tidak pas
**Solution**: Edit jenis ujian, pastikan total = 100%

#### 3. Photo upload gagal
**Cause**: File terlalu besar atau belum simpan draft
**Solution**: 
- Compress foto < 2MB
- Simpan draft formulir dulu

#### 4. "Cannot read property 'id' of null"
**Cause**: Data pendaftar tidak ditemukan
**Solution**: Check apakah user sudah punya data di tabel applicants

#### 5. PDF error / gambar tidak muncul
**Cause**: Path foto tidak ditemukan
**Solution**: Pastikan storage linked, dan foto ada di `storage/app/public/pmb/photos/`

---

## Contact & Maintainer

**Developer**: [Your Name]
**Email**: developer@institusi.ac.id
**Last Updated**: July 2026
**Version**: 1.0.0

---

**End of Technical Documentation**
