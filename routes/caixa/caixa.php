<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CaixaTurnoController;
use App\Http\Controllers\Api\CaixaMovimentoController;
use App\Http\Controllers\Api\CaixaRequisicaoController;
use App\Http\Controllers\Api\CaixaJustificativaController;

Route::prefix('caixa')->group(function () {
    
    // ========== TURNOS ==========
    Route::prefix('turnos')->group(function () {
        Route::get('/', [CaixaTurnoController::class, 'index']);
        Route::get('/verificar-aberto', [CaixaTurnoController::class, 'verificarTurnoAberto']);
        Route::post('/', [CaixaTurnoController::class, 'store']);
        Route::get('/{id}', [CaixaTurnoController::class, 'show']);
        Route::post('/{id}/fechar', [CaixaTurnoController::class, 'fechar']);
        Route::delete('/{id}', [CaixaTurnoController::class, 'destroy']);
    });
    
    // ========== MOVIMENTOS ==========
    Route::prefix('movimentos')->group(function () {
        Route::get('/', [CaixaMovimentoController::class, 'index']);
        Route::post('/pagar-requisicao', [CaixaMovimentoController::class, 'pagarRequisicao']);
        Route::post('/registrar-devolucao', [CaixaMovimentoController::class, 'registrarDevolucao']);
    });
    
    // ========== REQUISIÇÕES ==========
    Route::prefix('requisicoes')->group(function () {
        Route::get('/', [CaixaRequisicaoController::class, 'index']);
        Route::get('/pendentes', [CaixaRequisicaoController::class, 'pendentes']);
        Route::get('/viagens-pendencias', [CaixaRequisicaoController::class, 'viagensComPendencias']);
        Route::post('/', [CaixaRequisicaoController::class, 'store']);
        Route::post('/{id}/aprovar', [CaixaRequisicaoController::class, 'aprovar']);
        Route::post('/{id}/rejeitar', [CaixaRequisicaoController::class, 'rejeitar']);
        Route::put('/{id}', [CaixaRequisicaoController::class, 'update']);
        Route::delete('/{id}', [CaixaRequisicaoController::class, 'destroy']);
    });
    
    // ========== JUSTIFICATIVAS ==========
    Route::prefix('justificativas')->group(function () {
        Route::get('/', [CaixaJustificativaController::class, 'index']);
        Route::post('/', [CaixaJustificativaController::class, 'justificar']); // <-- ADICIONAR ESTA ROTA
    });
});