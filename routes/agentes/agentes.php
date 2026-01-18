<?php
// routes/agentes/agentes.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AgenteController;

Route::prefix('agentes')->group(function () {
    // Listar agentes com busca/paginação
    Route::get('/', [AgenteController::class, 'index']);
    
    // Criar novo agente
    Route::post('/', [AgenteController::class, 'store']);
    
    // Rotas que precisam de ID
    Route::prefix('{agente}')->group(function () {
        // Ver agente específico
        Route::get('/', [AgenteController::class, 'show']);
        
        // Atualizar agente
        Route::put('/', [AgenteController::class, 'update']);
        Route::patch('/', [AgenteController::class, 'update']);
        
        // Excluir agente
        Route::delete('/', [AgenteController::class, 'destroy']);
    });
});