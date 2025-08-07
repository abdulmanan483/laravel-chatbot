<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $fillable = [
        'session_id',
        'question_id',
        'message',
        'role',
    ];
    public function session()
    {
        return $this->belongsTo(ChatSession::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
