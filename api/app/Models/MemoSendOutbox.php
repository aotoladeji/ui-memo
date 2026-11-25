<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemoSendOutbox extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'recipient_ids' => 'array',
        'attempts' => 'integer',
        'dispatched_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function memo()
    {
        return $this->belongsTo(Memo::class);
    }

    public function route()
    {
        return $this->belongsTo(MemoRoute::class, 'route_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
