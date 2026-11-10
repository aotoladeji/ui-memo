<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemoDocument extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['page_dims' => 'array'];
}
