<?php

namespace App\Console\Commands;

use App\Models\ClassMember;
use App\Models\KrsDetail;
use Illuminate\Console\Command;

class SyncClassMembers extends Command
{
    protected $signature   = 'sync:class-members';
    protected $description = 'Sync class_members dari krs_details yang sudah ada';

    public function handle(): int
    {
        $synced = 0;
        $skipped = 0;

        KrsDetail::whereNotNull('class_id')
            ->where('status', 'AKTIF')
            ->with('krs')
            ->chunk(200, function ($details) use (&$synced, &$skipped) {
                foreach ($details as $detail) {
                    $krs = $detail->krs;
                    if (!$krs || !$krs->student_id) { $skipped++; continue; }

                    $created = ClassMember::firstOrCreate([
                        'class_id'   => $detail->class_id,
                        'student_id' => $krs->student_id,
                    ]);

                    if ($created->wasRecentlyCreated) {
                        $synced++;
                    } else {
                        $skipped++;
                    }
                }
            });

        $this->info("Sync selesai. Ditambahkan: {$synced}, Sudah ada: {$skipped}");
        return 0;
    }
}
