<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    protected $guarded = ['id', 'can_send_global_broadcast', 'role'];   // never mass-assignable
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['is_executive' => 'boolean', 'is_active' => 'boolean',
                        'can_send_global_broadcast' => 'boolean', 'password' => 'hashed'];

    public function unit() { return $this->belongsTo(Unit::class); }
    public function adminScopes() { return $this->hasMany(AdminScope::class); }
    public function isSuperAdmin(): bool { return $this->role === 'super_admin'; }
    public function mayBroadcast(): bool { return $this->isSuperAdmin() || $this->can_send_global_broadcast; }
}
