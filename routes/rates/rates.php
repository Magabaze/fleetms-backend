<?php
// routes/rate/rates.php 
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RateController;

Route::prefix('rates')->group(function () {
    // Listar rates
    Route::get('/', [RateController::class, 'index']);
    
    // Criar rate
    Route::post('/', [RateController::class, 'store']);
    
    // Buscar clientes e distâncias para selects
    Route::get('/clientes', [RateController::class, 'clientes']);
    Route::get('/distancias', [RateController::class, 'distancias']);
    
    // Outras rotas auxiliares
    Route::get('/buscar/cliente', [RateController::class, 'buscarRateCliente']);
    Route::get('/historico/cliente/{clienteId}', [RateController::class, 'historicoCliente']);
    
    // Rotas com ID
    Route::prefix('{rate}')->group(function () {
        // Ver rate específico
        Route::get('/', [RateController::class, 'show']);
        
        // Atualizar rate
        Route::put('/', [RateController::class, 'update']);
        
        // Excluir rate
        Route::delete('/', [RateController::class, 'destroy']);
        
        // Aprovar rate
        Route::put('/aprovar', [RateController::class, 'aprovar']);
        
        // Rejeitar rate
        Route::put('/rejeitar', [RateController::class, 'rejeitar']);
        
        // DESFAZER APROVAÇÃO - NOVA ROTA
        Route::put('/desfazer-aprovacao', [RateController::class, 'desfazerAprovacao']);
    });
});