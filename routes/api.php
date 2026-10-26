<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\DestinationController;
use App\Http\Controllers\Api\InsuranceController;
use App\Http\Controllers\Api\LegalPageController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\SettingController;
use Illuminate\Support\Facades\Route;

/*
| Le rotte pubbliche accettano opzionalmente il token Bearer di un'agenzia:
| se presente, le risposte includono la fee dell'agenzia e le pratiche
| vengono intestate all'agenzia.
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
    Route::get('legal-pages', [LegalPageController::class, 'index']);
    Route::get('legal-pages/{legalPage:slug}', [LegalPageController::class, 'show']);
    Route::get('insurances', InsuranceController::class);
    Route::get('destinations', [DestinationController::class, 'index']);
    Route::get('destinations/{destination:slug}', [DestinationController::class, 'show']);
    Route::get('packages', [PackageController::class, 'index']);
    Route::get('packages/filters', [PackageController::class, 'filters']);
    Route::get('packages/{package:slug}', [PackageController::class, 'show']);

    // Carrello e pratiche
    Route::post('cart/quote', [CartController::class, 'quote'])->middleware('throttle:120,1');
    Route::post('bookings', [BookingController::class, 'store'])->middleware('throttle:10,1');

    Route::get('bookings', [BookingController::class, 'index'])->middleware('auth:sanctum');

    Route::prefix('bookings/{booking:code}')->group(function () {
        Route::get('/', [BookingController::class, 'show']);
        Route::post('option', [BookingController::class, 'option'])->middleware('throttle:10,1');
        Route::post('confirm', [BookingController::class, 'confirm'])->middleware('throttle:10,1');
        Route::post('cancel', [BookingController::class, 'cancel'])->middleware('throttle:10,1');
        Route::get('quote.pdf', [BookingController::class, 'quotePdf'])->name('api.bookings.quote-pdf');
        Route::get('proforma', [BookingController::class, 'proforma'])->middleware('signed')->name('api.bookings.proforma');
    });
});
