<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Minute extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'memo_id',
        'origin_route_id',
        'author_id',
        'page_number',
        'x',
        'y',
        'body',
        'color',
        'classification',
    ];

    protected $casts = [
        'page_number' => 'integer',
        'x' => 'float',
        'y' => 'float',
    ];

    public function memo()
    {
        return $this->belongsTo(Memo::class);
    }

    public function originRoute()
    {
        return $this->belongsTo(MemoRoute::class, 'origin_route_id');
    }

    public function route()
    {
        return $this->belongsTo(MemoRoute::class, 'origin_route_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function replies()
    {
        return $this->hasMany(MinuteReply::class, 'minute_id');
    }
}
