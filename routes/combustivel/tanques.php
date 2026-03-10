<?php
// routes/api/combustivel/tanques.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Combustivel\TanqueController;

Route::prefix('tanques')->group(function () {
    // Recursos (tipos, status, unidades)
    Route::get('/recursos', [TanqueController::class, 'recursos']);
    
    // Listar tanques com busca/paginação
    Route::get('/', [TanqueController::class, 'index']);
    
    // Criar novo tanque
    Route::post('/', [TanqueController::class, 'store']);
    
    // Rotas que precisam de ID
    Route::prefix('{tanque}')->group(function () {
        // Ver tanque específico
        Route::get('/', [TanqueController::class, 'show']);
        
        // Atualizar tanque
        Route::put('/', [TanqueController::class, 'update']);
        Route::patch('/', [TanqueController::class, 'update']);
        
        // Excluir tanque
        Route::delete('/', [TanqueController::class, 'destroy']);
    });
});