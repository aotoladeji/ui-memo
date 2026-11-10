<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Faculty extends Model
{
    use SoftDeletes;
    protected $table = 'faculties';
    protected $guarded = ['id'];
    public function departments() { return $this->hasMany(Department::class); }
}
