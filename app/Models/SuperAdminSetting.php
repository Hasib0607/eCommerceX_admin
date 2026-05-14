<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuperAdminSetting extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'value', 'user_id'];

    /**
     * Optional: associate with user model.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Static method to get a setting value by name.
     */
    public static function getValue(string $name, $default = null)
    {
        return static::where('name', $name)->value('value') ?? $default;
    }

    /**
     * Static method to set a setting value by name.
     */
    public static function setValue(string $name, $value, ?int $userId = null)
    {
        $setting = static::firstOrNew(['name' => $name]);

        $setting->value = $value;
        $setting->user_id = $userId;
        $setting->save();

        return $setting;

    }

}
