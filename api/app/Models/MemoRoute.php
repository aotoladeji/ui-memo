<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemoRoute extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['hidden_classifications' => 'array'];

    public function rules() { return $this->hasMany(MemoForwardVisibilityRule::class, 'route_id'); }
    public function parent() { return $this->belongsTo(self::class, 'parent_route_id'); }
    public function sender() { return $this->belongsTo(User::class, 'from_user_id'); }
}
