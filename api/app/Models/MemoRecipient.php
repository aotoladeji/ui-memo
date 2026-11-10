<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemoRecipient extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['delivered_at' => 'datetime', 'read_at' => 'datetime'];

    public function route() { return $this->belongsTo(MemoRoute::class, 'route_id'); }
    public function memo() { return $this->belongsTo(Memo::class); }
    public function user() { return $this->belongsTo(User::class); }
}
