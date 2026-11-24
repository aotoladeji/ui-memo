<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MinuteReply extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'minute_id',
        'origin_route_id',
        'author_id',
        'body',
    ];

    public function minute()
    {
        return $this->belongsTo(Minute::class, 'minute_id');
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
}
