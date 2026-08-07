# REST API LMS

Base URL contoh: `https://lms.stai-aljawami.ac.id/api`

Semua respons menggunakan JSON UTF-8. Endpoint selain health check memerlukan header:

```http
Authorization: Bearer stai_xxxxxxxxxxxxxxxxx
Accept: application/json
```

Token dibuat dari menu **Integrasi API**. Rahasia token hanya ditampilkan satu kali.

## Endpoint

### GET `/health`

Tidak memerlukan autentikasi. Digunakan untuk memeriksa ketersediaan layanan.

### GET `/v1/programs`

Memerlukan scope `read`. Mengembalikan program studi.

### GET `/v1/courses`

Memerlukan scope `read`. Mengembalikan mata kuliah dan program studinya.

### GET `/v1/classes`

Memerlukan scope `read`. Mengembalikan kelas, semester, dosen, moda, jadwal, dan status.

### GET `/v1/enrollments`

Memerlukan scope `read`. Mengembalikan keanggotaan mahasiswa pada kelas.

### POST `/v1/sync/users`

Memerlukan scope `sync`. Maksimal 500 pengguna per permintaan.

```json
{
  "data": [
    {
      "external_id": "SIAKAD-2026110001",
      "username": "2026110001",
      "email": "mahasiswa@example.ac.id",
      "full_name": "Nama Mahasiswa",
      "identity_number": "2026110001",
      "role": "mahasiswa",
      "program_code": "PAI",
      "phone": "081234567890",
      "is_active": true,
      "initial_password": "KataSandiAwal!"
    }
  ]
}
```

Nilai `role` yang diterima: `super_admin`, `admin`, `dosen`, `mahasiswa`, `kaprodi`, dan `lpm`.

### POST `/v1/sync/courses`

Memerlukan scope `sync`. Setiap item memerlukan `external_id`, `code`, dan `name`. Kolom lain: `program_code`, `credits`, `semester_number`, `description`, dan `is_active`.

```json
{"data":[{"external_id":"MK-PAI-101","program_code":"PAI","code":"PAI-101","name":"Pengantar Studi Islam","credits":2,"semester_number":1,"is_active":true}]}
```

### POST `/v1/sync/classes`

Memerlukan scope `sync`. Sinkronkan pengguna dan mata kuliah lebih dahulu. Setiap kelas memerlukan `external_id`, `course_external_id`, `academic_year`, `semester`, dan `name`. Kolom opsional mencakup `lecturer_external_id`, `mode`, `room`, `meeting_url`, `schedule_day`, `schedule_time`, dan `status`.

```json
{"data":[{"external_id":"KLS-2026-PAI101-A","course_external_id":"MK-PAI-101","academic_year":"2026/2027","semester":"ganjil","lecturer_external_id":"SIAKAD-DOSEN-001","name":"A","mode":"hybrid","schedule_day":"Sabtu","schedule_time":"08.00–10.30 WIB","status":"active"}]}
```

### POST `/v1/sync/enrollments`

Memerlukan scope `sync`. Setiap item memerlukan `external_id`, `class_external_id`, dan `student_external_id`; status dapat bernilai `active`, `dropped`, atau `completed`.

```json
{"data":[{"external_id":"KRS-2026-0001","class_external_id":"KLS-2026-PAI101-A","student_external_id":"SIAKAD-2026110001","status":"active"}]}
```

## Respons kesalahan

```json
{
  "ok": false,
  "message": "Penjelasan kesalahan"
}
```

Status HTTP yang digunakan antara lain 400, 401, 403, 404, 422, dan 500.

## Rekomendasi integrasi SIAKAD

- Gunakan `external_id` sebagai identitas stabil dari SIAKAD.
- Jalankan sinkronisasi server-ke-server melalui HTTPS.
- Gunakan token terpisah untuk pengujian dan produksi.
- Batasi token pengujian dengan tanggal kedaluwarsa.
- Jangan menggunakan email atau username sebagai satu-satunya kunci sinkronisasi.
- Dokumentasikan pemetaan program studi, mata kuliah, kelas, dan status mahasiswa sebelum mengaktifkan sinkronisasi otomatis.
