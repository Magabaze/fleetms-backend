<?php
// routes/clientes/clientes.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ClienteController;

Route::prefix('clientes')->group(function () {
    // Listar clientes com busca/paginação
    Route::get('/', [ClienteController::class, 'index']);
    
    // Criar novo cliente
    Route::post('/', [ClienteController::class, 'store']);
    
    // Rotas que precisam de ID
    Route::prefix('{cliente}')->group(function () {
        // Ver cliente específico
        Route::get('/', [ClienteController::class, 'show']);
        
        // Atualizar cliente
        Route::put('/', [ClienteController::class, 'update']);
        Route::patch('/', [ClienteController::class, 'update']);
        
        // Excluir cliente
        Route::delete('/', [ClienteController::class, 'destroy']);
    });
    
    // Rotas adicionais
    Route::get('/tipos', function () {
        return response()->json([
            'tipos' => ['Consignee', 'Shipper', 'Invoice Party']
        ]);
    });
});