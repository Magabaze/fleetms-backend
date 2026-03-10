<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Manutencao\OrcamentoController;

Route::prefix('orcamentos')->group(function () {
    Route::get('/', [OrcamentoController::class, 'index']);
    Route::post('/', [OrcamentoController::class, 'store']);
    
    Route::prefix('{orcamento}')->group(function () {
        Route::get('/', [OrcamentoController::class, 'show']);
        Route::put('/', [OrcamentoController::class, 'update']);
        Route::patch('/', [OrcamentoController::class, 'update']);
        Route::delete('/', [OrcamentoController::class, 'destroy']);
        Route::patch('/status', [OrcamentoController::class, 'alterarStatus']);
    });
});