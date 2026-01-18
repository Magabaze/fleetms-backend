<?php
// routes/cargas/cargas.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CargaController;

Route::prefix('cargas')->group(function () {
    // Listar cargas com busca/paginação
    Route::get('/', [CargaController::class, 'index']);
    
    // Criar nova carga
    Route::post('/', [CargaController::class, 'store']);
    
    // Rotas que precisam de ID
    Route::prefix('{carga}')->group(function () {
        // Ver carga específica
        Route::get('/', [CargaController::class, 'show']);
        
        // Atualizar carga
        Route::put('/', [CargaController::class, 'update']);
        Route::patch('/', [CargaController::class, 'update']);
        
        // Excluir carga
        Route::delete('/', [CargaController::class, 'destroy']);
    });
});