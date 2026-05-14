<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatConversation extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function message()
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function visitor()
    {
        return $this->belongsTo(ChatVisitor::class, 'visitor_id', 'id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }


}
