<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Lecturer;

class NotificationService
{
    /**
     * Notifikasi ke dosen saat ditugaskan mengampu mata kuliah
     */
    public static function classAssigned(int $lecturerId, string $courseName, string $className, string $semesterName): void
    {
        $lecturer = Lecturer::find($lecturerId);
        if (!$lecturer || !$lecturer->user_id) return;

        AppNotification::send(
            $lecturer->user_id,
            'Penugasan Mengampu Mata Kuliah',
            "Anda ditugaskan mengampu {$courseName} ({$className}) untuk {$semesterName}.",
            'info',
            '/akademik/kelas'
        );
    }

    /**
     * Notifikasi ke dosen saat ditunjuk sebagai dosen wali/pembimbing akademik mahasiswa
     */
    public static function advisorAssigned(int $lecturerId, string $studentName, string $studentNim): void
    {
        $lecturer = Lecturer::find($lecturerId);
        if (!$lecturer || !$lecturer->user_id) return;

        AppNotification::send(
            $lecturer->user_id,
            'Penunjukan Dosen Wali',
            "Anda ditunjuk sebagai dosen wali untuk mahasiswa {$studentName} ({$studentNim}).",
            'info',
            '/bimbingan/mahasiswa'
        );
    }

    /**
     * Notifikasi ke dosen saat ditunjuk sebagai pembimbing skripsi
     */
    public static function thesisSupervisorAssigned(int $lecturerId, string $studentName, string $thesisTitle, string $role): void
    {
        $lecturer = Lecturer::find($lecturerId);
        if (!$lecturer || !$lecturer->user_id) return;

        $roleName = match ($role) {
            'PEMBIMBING_1' => 'Pembimbing 1',
            'PEMBIMBING_2' => 'Pembimbing 2',
            'PEMBIMBING_3' => 'Pembimbing 3',
            default => 'Pembimbing',
        };

        AppNotification::send(
            $lecturer->user_id,
            "Penunjukan {$roleName} Skripsi",
            "Anda ditunjuk sebagai {$roleName} skripsi mahasiswa {$studentName}: \"{$thesisTitle}\".",
            'info',
            '/skripsi'
        );
    }

    /**
     * Notifikasi ke dosen saat ditunjuk sebagai penguji skripsi
     */
    public static function thesisExaminerAssigned(int $lecturerId, string $studentName, string $thesisTitle, string $role): void
    {
        $lecturer = Lecturer::find($lecturerId);
        if (!$lecturer || !$lecturer->user_id) return;

        $roleName = match ($role) {
            'KETUA_PENGUJI' => 'Ketua Penguji',
            'PENGUJI_1' => 'Penguji 1',
            'PENGUJI_2' => 'Penguji 2',
            'SEKRETARIS' => 'Sekretaris',
            default => 'Penguji',
        };

        AppNotification::send(
            $lecturer->user_id,
            "Penunjukan {$roleName} Sidang Skripsi",
            "Anda ditunjuk sebagai {$roleName} sidang skripsi mahasiswa {$studentName}: \"{$thesisTitle}\".",
            'warning',
            '/skripsi'
        );
    }
}
