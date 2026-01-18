<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ViagemController;

Route::prefix('viagens')->group(function () {
    // Buscar recursos para criar viagem
    Route::get('/recursos', [ViagemController::class, 'recursos']);
    
    // Listar viagens
    Route::get('/', [ViagemController::class, 'index']);
    
    // Criar viagem
    Route::post('/', [ViagemController::class, 'store']);
    
    // ✅ REMOVER A ROTA DE PRINT DAQUI - Ela já está no arquivo principal api.php
    
    // Rotas com ID
    Route::prefix('{viagem}')->group(function () {
        // Ver viagem específica
        Route::get('/', [ViagemController::class, 'show']);
        
        // Atualizar viagem
        Route::put('/', [ViagemController::class, 'update']);
        
        // Excluir viagem
        Route::delete('/', [ViagemController::class, 'destroy']);
        
        // Atualizar tracking
        Route::patch('/tracking', [ViagemController::class, 'atualizarTracking']);
        
        // Fechar viagem
        Route::put('/fechar', [ViagemController::class, 'fecharViagem']);
        
        // Adicionar nova leg
        Route::post('/nova-leg', [ViagemController::class, 'adicionarLeg']);
        
        // Alterar destino (para viagens vazias)
        Route::put('/alterar-destino', [ViagemController::class, 'alterarDestino']);
        
        // Alterar ordem (para viagens com carga)
        Route::put('/alterar-ordem', [ViagemController::class, 'alterarOrdem']);
        
        // Desfazer viagem (Undo Trip)
        Route::put('/desfazer', [ViagemController::class, 'desfazerViagem']);
        
        // Adicionar KM extra
        Route::put('/km-extra', [ViagemController::class, 'adicionarKMExtra']);
    });
});