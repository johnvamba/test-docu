<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentImportController;
use App\Http\Controllers\DocumentShareController;
use App\Http\Controllers\UserDirectoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/users', UserDirectoryController::class);

    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/documents/import', [DocumentImportController::class, 'store']);
        Route::post('/documents/{document}/import', [DocumentImportController::class, 'convert']);
    });

    Route::apiResource('documents', DocumentController::class);

    Route::post('/documents/{document}/shares', [DocumentShareController::class, 'store']);
    Route::patch('/documents/{document}/shares/{user}', [DocumentShareController::class, 'update']);
    Route::delete('/documents/{document}/shares/{user}', [DocumentShareController::class, 'destroy']);
});
