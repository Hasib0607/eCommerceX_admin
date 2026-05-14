<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatbotQuestion extends Model
{
    use HasFactory;

    protected $guarded = [];


    public function answers()
    {
        return $this->belongsToMany(ChatbotAnswer::class, 'chatbot_question_answers', 'question_id', 'answer_id')->withPivot('group_id');
    }

    public function answer()
    {
        return $this->hasMany(ChatbotAnswer::class, 'question_id', 'id');
    }

}
