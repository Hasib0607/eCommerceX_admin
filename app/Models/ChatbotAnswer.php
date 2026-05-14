<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatbotAnswer extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function questions()
    {
        return $this->belongsToMany(ChatbotQuestion::class, 'chatbot_question_answers', 'answer_id', 'question_id')->withPivot('group_id');
    }

}
