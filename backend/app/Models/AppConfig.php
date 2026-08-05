<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AppConfig extends Model
{
    protected $table = 'configs';
    protected $fillable = ['key', 'value', 'type', 'group', 'description'];
    
    public static function get(string $key, $default = null)
    {
        $config = self::where('key', $key)->first();
        if (!$config) return $default;
        return match($config->type) {
            'integer' => (int) $config->value,
            'boolean' => filter_var($config->value, FILTER_VALIDATE_BOOLEAN),
            'json'    => json_decode($config->value, true),
            default   => $config->value,
        };
    }

    public static function set(string $key, $value, string $type = 'string', string $group = 'general'): self
    {
        $val = is_array($value) ? json_encode($value) : (string) $value;
        return self::updateOrCreate(['key' => $key], ['value' => $val, 'type' => $type, 'group' => $group]);
    }
}
