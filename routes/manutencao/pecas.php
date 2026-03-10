<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Manutencao\PecaController;

Route::prefix('pecas')->group(function () {
    Route::get('/', [PecaController::class, 'index']);
    Route::post('/', [PecaController::class, 'store']);
    
    Route::prefix('{peca}')->group(function () {
        Route::get('/', [PecaController::class, 'show']);
        Route::put('/', [PecaController::class, 'update']);
        Route::patch('/', [PecaController::class, 'update']);
        Route::delete('/', [PecaController::class, 'destroy']);
        Route::post('/entrada', [PecaController::class, 'entradaStock']);
    });
});