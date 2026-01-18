<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TipoDespesaController;

Route::prefix('tipos-despesa')->group(function () {
    // Listar todos os tipos
    Route::get('/', [TipoDespesaController::class, 'index']);
    
    // Listar com paginação
    Route::get('/paginado', [TipoDespesaController::class, 'paginado']);
    
    // Buscar por ID
    Route::get('/{id}', [TipoDespesaController::class, 'show']);
    
    // Criar novo tipo
    Route::post('/', [TipoDespesaController::class, 'store']);
    
    // Atualizar tipo
    Route::put('/{id}', [TipoDespesaController::class, 'update']);
    
    // Excluir tipo
    Route::delete('/{id}', [TipoDespesaController::class, 'destroy']);
    
    // Verificar se nome já existe
    Route::get('/verificar-nome', [TipoDespesaController::class, 'verificarNome']);
    
    // Gerar tipos padrão
    Route::post('/gerar-padrao', [TipoDespesaController::class, 'gerarTiposPadrao']);
    
    // Teste
    Route::get('/test', [TipoDespesaController::class, 'test']);
});