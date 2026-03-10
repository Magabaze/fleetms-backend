<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Manutencao\AvariaController;

Route::prefix('avarias')->group(function () {
    Route::get('/', [AvariaController::class, 'index']);
    Route::post('/', [AvariaController::class, 'store']);
    
    Route::prefix('{avaria}')->group(function () {
        Route::get('/', [AvariaController::class, 'show']);
        Route::put('/', [AvariaController::class, 'update']);
        Route::patch('/', [AvariaController::class, 'update']);
        Route::delete('/', [AvariaController::class, 'destroy']);
    });
});