<?php

use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\OrganizationSyncController;
use App\Http\Controllers\Api\ReviewController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', function (Request $request): array {
        return [
            'data' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
        ];
    })->name('api.me');

    Route::get('/organization', [OrganizationController::class, 'show'])
        ->name('api.organization.show');
    Route::put('/organization', [OrganizationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('api.organization.store');
    Route::post('/organization/sync', OrganizationSyncController::class)
        ->middleware('throttle:10,1')
        ->name('api.organization.sync');
    Route::get('/organization/reviews', [ReviewController::class, 'index'])
        ->name('api.organization.reviews.index');
});
