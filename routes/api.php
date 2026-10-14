<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\DestinationController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\SettingController;
use Illuminate\Support\Facades\Route;

/*
| Le rotte pubbliche accettano opzionalmente il token Bearer di un'agenzia:
| se presente, i prezzi restituiti sono netti invece che lordi.
*/

Route::prefix('v1')->group(function () {
    // Autenticazione agenzie
    Route::prefix('auth')->group(function () {
        Route::middleware('throttle:10,1')->group(function () {
            Route::post('register', [AuthController::class, 'register']);
            Route::post('login', [AuthController::class, 'login']);
        });

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::put('me', [AuthController::class, 'update']);
        });
    });

    // Catalogo
    Route::get('settings', SettingController::class);
    Route::get('destinations', [DestinationController::class, 'index']);
    Route::get('destinations/{destination:slug}', [DestinationController::class, 'show']);
    Route::get('packages', [PackageController::class, 'index']);
    Route::get('packages/filters', [PackageController::class, 'filters']);
    Route::get('packages/{package:slug}', [PackageController::class, 'show']);
    Route::post('packages/{package:slug}/quote', [PackageController::class, 'quote'])->middleware('throttle:60,1');

    // Prenotazioni
    Route::post('bookings', [BookingController::class, 'store'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('bookings', [BookingController::class, 'index']);
        Route::get('bookings/{booking:code}', [BookingController::class, 'show']);
    });
});
