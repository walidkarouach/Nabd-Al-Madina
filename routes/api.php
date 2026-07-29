<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SignalementController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/signalements/similaires', [SignalementController::class, 'similaires']);
    Route::get('/signalements', [SignalementController::class, 'index']);
    Route::post('/signalements', [SignalementController::class, 'store']);
    Route::get('/signalements/{signalement}', [SignalementController::class, 'show']);
    Route::put('/signalements/{signalement}', [SignalementController::class, 'update']);
    Route::patch('/signalements/{signalement}', [SignalementController::class, 'update']);
    Route::patch('/signalements/{signalement}/status', [SignalementController::class, 'updateStatus']);
});