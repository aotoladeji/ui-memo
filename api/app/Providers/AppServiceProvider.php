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

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Database\Events\ConnectionEstablished::class, function ($event) {
            if ($event->connection instanceof \Illuminate\Database\SQLiteConnection) {
                $pdo = $event->connection->getPdo();
                $pdo->sqliteCreateFunction('JSON_CONTAINS', function ($target, $candidate) {
                    if ($target === null) return 0;
                    $targetArr = json_decode($target, true);
                    if (!is_array($targetArr)) return 0;
                    $val = json_decode($candidate, true);
                    if ($val === null && $candidate !== 'null') {
                        $val = trim($candidate, '"');
                    }
                    return in_array($val, $targetArr, false) ? 1 : 0;
                });
                $pdo->sqliteCreateFunction('JSON_ARRAY', function (...$args) {
                    return json_encode($args);
                });
                $pdo->sqliteCreateFunction('JSON_QUOTE', function ($val) {
                    return json_encode($val);
                });
            }
        });
    }
}
