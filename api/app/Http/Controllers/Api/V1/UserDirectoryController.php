<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserDirectoryController extends Controller
{
    /** Recipient picker. Returns minimal fields only. */
    public function index(Request $r)
    {
        $s = trim((string) $r->query('q', ''));
        return User::where('is_active', 1)->where('id', '!=', $r->user()->id)
            ->when($s, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('staff_no', 'like', "$s%")))
            ->orderBy('name')->limit(20)->get(['id', 'name', 'title', 'unit_id']);
    }
}
