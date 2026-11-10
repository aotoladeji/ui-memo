<?php

use App\Http\Controllers\Api\V1\{AnnotationController, AuthController, ForwardController, MemoController, NoteController, UserDirectoryController};
use App\Http\Controllers\Api\V1\Admin\{OrgController, UserAdminController};
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('users/search', [UserDirectoryController::class, 'index']);

        // Memos
        Route::get('memos', [MemoController::class, 'index']);
        Route::post('memos', [MemoController::class, 'store']);
        Route::get('memos/{memo}', [MemoController::class, 'show']);
        Route::get('memos/{memo}/file', [MemoController::class, 'file']);
        Route::post('memos/{memo}/send', [MemoController::class, 'send']);
        Route::post('memos/{memo}/complete', [ForwardController::class, 'complete']);

        // Annotations, notes, replies
        Route::put('memos/{memo}/annotations', [AnnotationController::class, 'sync'])->middleware('throttle:120,1');
        Route::delete('annotations/{annotation}', [AnnotationController::class, 'destroy']);
        Route::post('memos/{memo}/notes', [NoteController::class, 'store']);
        Route::patch('notes/{note}', [NoteController::class, 'update']);
        Route::post('notes/{note}/replies', [NoteController::class, 'reply'])->middleware('throttle:60,1');

        // Forwarding with selective masking
        Route::post('memos/{memo}/forward', [ForwardController::class, 'store']);
        Route::post('memos/{memo}/forward-preview', [ForwardController::class, 'preview']);

        // Administration
        Route::prefix('admin')->group(function () {
            Route::middleware('role:super_admin,admin')->group(function () {
                Route::get('org', [OrgController::class, 'tree']);
                Route::post('departments', [OrgController::class, 'storeDepartment']);
                Route::patch('departments/{department}', [OrgController::class, 'updateDepartment']);
                Route::delete('departments/{department}', [OrgController::class, 'destroyDepartment']);
                Route::post('units', [OrgController::class, 'storeUnit']);
                Route::patch('units/{unit}', [OrgController::class, 'updateUnit']);
                Route::delete('units/{unit}', [OrgController::class, 'destroyUnit']);
                Route::get('users', [UserAdminController::class, 'index']);
                Route::post('users', [UserAdminController::class, 'store']);
                Route::patch('users/{user}', [UserAdminController::class, 'update']);
                Route::delete('users/{user}', [UserAdminController::class, 'destroy']);
            });
            Route::middleware('role:super_admin')->group(function () {
                Route::post('faculties', [OrgController::class, 'storeFaculty']);
                Route::patch('faculties/{faculty}', [OrgController::class, 'updateFaculty']);
                Route::delete('faculties/{faculty}', [OrgController::class, 'destroyFaculty']);
                Route::patch('users/{user}/permissions', [UserAdminController::class, 'permissions']);
                Route::post('admins/{user}/scopes', [UserAdminController::class, 'assignScope']);
                Route::delete('admins/{user}/scopes/{scope}', [UserAdminController::class, 'removeScope']);
            });
        });
    });
});
