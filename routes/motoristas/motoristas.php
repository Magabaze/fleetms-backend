<?php
// routes/motoristas/motoristas.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MotoristaController;

Route::prefix('motoristas')->group(function () {
    // POST para criar deve vir antes do parâmetro {id}
    Route::post('/', [MotoristaController::class, 'store']);
    
    // GET para listar todos
    Route::get('/', [MotoristaController::class, 'index']);
    
    // Rotas que requerem o ID do motorista
    Route::prefix('{id}')->group(function () {
        // Detalhes de um motorista específico
        Route::get('/', [MotoristaController::class, 'show']);
        
        // Atualizar (Suporta PUT, PATCH e POST para compatibilidade com FormData)
        Route::put('/', [MotoristaController::class, 'update']);
        Route::patch('/', [MotoristaController::class, 'update']);
        Route::post('/', [MotoristaController::class, 'update']);
        
        // Deletar
        Route::delete('/', [MotoristaController::class, 'destroy']);
        
        // Visualizar documento específico (foto, carta ou passaporte)
        Route::get('/documento/{tipo}', [MotoristaController::class, 'visualizarDocumento'])
            ->where('tipo', 'foto|carta|passaporte');
    });
});