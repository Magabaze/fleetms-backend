<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Manutencao\ManutencaoExternaController;

Route::prefix('externa')->group(function () {
    Route::get('/', [ManutencaoExternaController::class, 'index']);
    Route::post('/', [ManutencaoExternaController::class, 'store']);
    
    Route::prefix('{externa}')->group(function () {
        Route::get('/', [ManutencaoExternaController::class, 'show']);
        Route::put('/', [ManutencaoExternaController::class, 'update']);
        Route::patch('/', [ManutencaoExternaController::class, 'update']);
        Route::delete('/', [ManutencaoExternaController::class, 'destroy']);
    });
    
    Route::post('/{id}/registrar-retorno', [ManutencaoExternaController::class, 'registrarRetorno']);
});