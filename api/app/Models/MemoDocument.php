<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemoDocument extends Model
{
    protected $fillable = [
        'memo_id',
        'kind',
        'storage_path',
        'mime',
        'sha256',
        'page_count',
        'page_dims',
        'template_data',
    ];

    protected $casts = [
        'page_dims' => 'array',
        'template_data' => 'array',
        'page_count' => 'integer',
    ];

    public function memo()
    {
        return $this->belongsTo(Memo::class);
    }
}
