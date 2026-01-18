<?php
// routes/despesas/despesas.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DespesaMotoristaController;

Route::prefix('despesas-motoristas')->group(function () {
    // Listar rotas com despesas (paginação)
    Route::get('/', [DespesaMotoristaController::class, 'index']);
    
    // Listar distâncias disponíveis (para adicionar novas rotas)
    Route::get('/distancias-disponiveis', [DespesaMotoristaController::class, 'distanciasDisponiveis']);
    
    // CRUD de despesas
    Route::post('/despesas', [DespesaMotoristaController::class, 'storeDespesa']);
    Route::put('/despesas/{id}', [DespesaMotoristaController::class, 'updateDespesa']);
    Route::delete('/despesas/{id}', [DespesaMotoristaController::class, 'destroyDespesa']);
    
    // Tipos de despesa
    Route::get('/tipos', [DespesaMotoristaController::class, 'tiposDespesa']);
    
    // Teste
    Route::get('/test', [DespesaMotoristaController::class, 'test']);
});