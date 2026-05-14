<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ChatVisitor extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($visitor) {
            // Generate a unique session token
            $visitor->session_token = static::generateUniqueToken();
        });
    }

    /**
     * Generate a unique session token.
     *
     * @return string
     */
    protected static function generateUniqueToken()
    {
        do {
            $token = Str::random(60); // You can use Str::uuid() if you prefer UUIDs
        } while (static::where('session_token', $token)->exists());

        return $token;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function store()
    {
        return $this->hasOne(Store::class, 'user_id', 'user_id');
    }

}
