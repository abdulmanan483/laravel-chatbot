<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'ended_at'
    ];
    protected $dates = [
        'ended_at',
    ];
    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'session_id');
    }
}
