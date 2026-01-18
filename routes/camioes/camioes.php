<?php
// routes/camioes/camioes.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CamiaoController;
use App\Http\Controllers\Api\TrelaController;

Route::prefix('camioes')->group(function () {
    Route::get('/', [CamiaoController::class, 'index']);
    Route::post('/', [CamiaoController::class, 'store']);
    
    Route::prefix('{camiao}')->group(function () {
        Route::get('/', [CamiaoController::class, 'show']);
        Route::put('/', [CamiaoController::class, 'update']);
        Route::patch('/', [CamiaoController::class, 'update']);
        Route::delete('/', [CamiaoController::class, 'destroy']);
    });
});

Route::prefix('trelas')->group(function () {
    Route::get('/', [TrelaController::class, 'index']);
    Route::post('/', [TrelaController::class, 'store']);
    
    Route::prefix('{trela}')->group(function () {
        Route::get('/', [TrelaController::class, 'show']);
        Route::put('/', [TrelaController::class, 'update']);
        Route::patch('/', [TrelaController::class, 'update']);
        Route::delete('/', [TrelaController::class, 'destroy']);
    });
});