<?php

namespace App\Providers;

use App\Http\Middleware\EnsureRole;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Route::aliasMiddleware('role', EnsureRole::class);
    }
}
