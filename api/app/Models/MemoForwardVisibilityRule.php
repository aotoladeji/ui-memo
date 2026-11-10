<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemoForwardVisibilityRule extends Model
{
    public $timestamps = false;
    protected $table = 'memo_forward_visibility_rules';
    protected $guarded = ['id'];
}
