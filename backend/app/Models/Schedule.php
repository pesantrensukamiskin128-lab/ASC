<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    protected $fillable = ['class_id', 'day', 'start_time', 'end_time', 'room_id', 'lecturer_id', 'note', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    
    public function class_(): BelongsTo { return $this->belongsTo(ClassModel::class, 'class_id'); }
    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
    public function lecturer(): BelongsTo { return $this->belongsTo(Lecturer::class); }

    public static function hasConflict(int $semesterId, string $day, string $startTime, string $endTime, ?int $lecturerId = null, ?int $roomId = null, ?int $excludeId = null): array
    {
        $conflicts = [];
        $query = self::whereHas('class_', fn($q) => $q->where('semester_id', $semesterId))
            ->where('day', $day)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->where('is_active', true);
        if ($excludeId) $query->where('id', '!=', $excludeId);

        if ($lecturerId) {
            $conflict = (clone $query)->where('lecturer_id', $lecturerId)->with('class_.course')->first();
            if ($conflict) $conflicts[] = "Dosen sudah mengajar {$conflict->class_->course->name} pada waktu tersebut.";
        }
        if ($roomId) {
            $conflict = (clone $query)->where('room_id', $roomId)->with(['class_.course', 'room'])->first();
            if ($conflict) $conflicts[] = "Ruangan {$conflict->room->name} sudah dipakai untuk {$conflict->class_->course->name}.";
        }
        return $conflicts;
    }
}
