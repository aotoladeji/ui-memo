<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        $u = User::where('email', $d['email'])->where('is_active', 1)->first();
        abort_unless($u && Hash::check($d['password'], $u->password), 422, 'Email or password is incorrect.');
        return ['token' => $u->createToken('spa')->plainTextToken, 'user' => $this->shape($u)];
    }

    public function me(Request $r) { return $this->shape($r->user()); }

    public function logout(Request $r) { $r->user()->currentAccessToken()->delete(); return response()->noContent(); }

    private function shape(User $u): array
    {
        return $u->only('id', 'name', 'email', 'role', 'title', 'unit_id', 'is_executive', 'can_send_global_broadcast');
    }
}
