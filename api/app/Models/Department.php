<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use SoftDeletes;
    protected $table = 'departments';
    protected $guarded = ['id'];
    public function faculty() { return $this->belongsTo(Faculty::class); }
    public function units() { return $this->hasMany(Unit::class); }
}
