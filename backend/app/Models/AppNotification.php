<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $table = 'notifications';
    protected $fillable = ['user_id', 'title', 'message', 'type', 'link', 'icon', 'is_read', 'read_at'];
    protected $casts = ['is_read' => 'boolean', 'read_at' => 'datetime'];
    
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    
    public function markAsRead(): void
    {
        $this->update(['is_read' => true, 'read_at' => now()]);
    }

    public static function send(int $userId, string $title, ?string $message = null, string $type = 'info', ?string $link = null): self
    {
        return self::create([
            'user_id' => $userId,
            'title'   => $title,
            'message' => $message,
            'type'    => $type,
            'link'    => $link,
        ]);
    }
}
