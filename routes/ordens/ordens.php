<?php
// routes/ordens/ordens.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OrdemController;

Route::prefix('ordens')->group(function () {
    // Listar ordens com busca/paginação
    Route::get('/', [OrdemController::class, 'index']);
    
    // Criar nova ordem
    Route::post('/', [OrdemController::class, 'store']);
    
    // APIs auxiliares
    Route::get('/clientes/select', [OrdemController::class, 'clientesSelect']);
    Route::get('/commodities', [OrdemController::class, 'commodities']);
    
    // Rotas que precisam de ID
    Route::prefix('{ordem}')->group(function () {
        // Ver ordem específica
        Route::get('/', [OrdemController::class, 'show']);
        
        // Atualizar ordem
        Route::put('/', [OrdemController::class, 'update']);
        Route::patch('/', [OrdemController::class, 'update']);
        
        // Atualizar status
        Route::patch('/status', [OrdemController::class, 'updateStatus']);
        
        // Excluir ordem
        Route::delete('/', [OrdemController::class, 'destroy']);
    });
});