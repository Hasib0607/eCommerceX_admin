<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatbotQuestionAnswer extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function question()
    {
        return $this->belongsTo(ChatbotQuestion::class);
    }

    public function answer()
    {
        return $this->belongsTo(ChatbotAnswer::class);
    }

}
