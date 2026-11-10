<?php

namespace App\Models;

use App\Models\Concerns\ChainScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Annotation extends Model
{
    use ChainScoped, SoftDeletes;
    protected $guarded = ['id'];
    protected $casts = ['geometry' => 'array', 'style' => 'array'];
}
