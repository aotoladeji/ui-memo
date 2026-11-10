<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StickyNoteReply extends Model
{
    use SoftDeletes;
    protected $guarded = ['id'];
    public function note() { return $this->belongsTo(StickyNote::class, 'note_id'); }
}
