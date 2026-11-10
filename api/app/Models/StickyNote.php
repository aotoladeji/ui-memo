<?php

namespace App\Models;

use App\Models\Concerns\ChainScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StickyNote extends Model
{
    use ChainScoped, SoftDeletes;
    protected $guarded = ['id'];
    protected $casts = ['x' => 'float', 'y' => 'float', 'requires_action' => 'boolean', 'resolved_at' => 'datetime'];

    public function replies() { return $this->hasMany(StickyNoteReply::class, 'note_id'); }
}
