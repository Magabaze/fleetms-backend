<?php
// routes/motoristas/motoristas.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MotoristaController;

Route::prefix('motoristas')->group(function () {
    Route::get('/', [MotoristaController::class, 'index']);
    Route::post('/', [MotoristaController::class, 'store']);
    
    Route::prefix('{motorista}')->group(function () {
        Route::get('/', [MotoristaController::class, 'show']);
        Route::put('/', [MotoristaController::class, 'update']);
        Route::patch('/', [MotoristaController::class, 'update']);
        Route::delete('/', [MotoristaController::class, 'destroy']);
        
        // Rota para visualizar documentos
        Route::get('/documento/{tipo}', [MotoristaController::class, 'visualizarDocumento'])
            ->where('tipo', 'foto|carta|passaporte');
    });
});