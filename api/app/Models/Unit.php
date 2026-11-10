<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use SoftDeletes;
    protected $table = 'units';
    protected $guarded = ['id'];
    public function department() { return $this->belongsTo(Department::class); }
    public function members() { return $this->hasMany(User::class); }
}
