<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DriverExpenseController;

// Rotas específicas para Driver Expenses (despesas de motoristas)
Route::prefix('driver-expenses')->group(function () {
    // Test endpoint
    Route::get('/test', [DriverExpenseController::class, 'test']);
    
    // Tipos de despesa disponíveis
    Route::get('/tipos-despesa', [DriverExpenseController::class, 'tiposDespesa']);
    
    // Operações em lote
    Route::post('/aprovar-lote', [DriverExpenseController::class, 'aprovarLote']);
    Route::post('/cancelar-lote', [DriverExpenseController::class, 'cancelarLote']);
});

// Rotas para despesas por viagem (compatível com seu serviço front-end)
Route::prefix('viagens')->group(function () {
    Route::prefix('{viagemId}/despesas')->group(function () {
        // Listar despesas da viagem
        Route::get('/', [DriverExpenseController::class, 'buscarPorViagem']);
        
        // Criar nova despesa
        Route::post('/', [DriverExpenseController::class, 'criarParaViagem']);
        
        // Aprovar todas as despesas da viagem
        Route::post('/aprovar-todas', [DriverExpenseController::class, 'aprovarTodas']);
        
        // Deletar despesa específica
        Route::delete('/{id}', [DriverExpenseController::class, 'deletar']);
    });
});

// Rotas compatíveis com o nome atual (para manter compatibilidade)
Route::prefix('despesas-motoristas')->group(function () {
    Route::post('/aprovar-lote', [DriverExpenseController::class, 'aprovarLote']);
    Route::post('/cancelar-lote', [DriverExpenseController::class, 'cancelarLote']);
});