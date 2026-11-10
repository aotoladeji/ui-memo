<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Memo extends Model
{
    use SoftDeletes;
    protected $guarded = ['id'];
    protected $casts = ['sent_at' => 'datetime'];

    public function author() { return $this->belongsTo(User::class, 'created_by'); }
    public function document() { return $this->hasOne(MemoDocument::class)->latestOfMany(); }
    public function routes() { return $this->hasMany(MemoRoute::class); }
    public function recipients() { return $this->hasMany(MemoRecipient::class); }

    /** Row-level security: a memo exists for a user only if they hold a recipient row. */
    public function scopeVisibleTo(Builder $q, User $u): Builder
    {
        return $q->whereIn('memos.id', MemoRecipient::select('memo_id')->where('user_id', $u->id));
    }
}
