<?php
// routes/faturacao/faturacao.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OrdemFaturacaoController;
use App\Http\Controllers\Api\NotaFiscalController;

Route::prefix('ordens-faturacao')->group(function () {
    Route::get('/', [OrdemFaturacaoController::class, 'index']);
    Route::post('/', [OrdemFaturacaoController::class, 'store']);
    
    Route::prefix('{ordem}')->group(function () {
        Route::get('/', [OrdemFaturacaoController::class, 'show']);
        Route::put('/', [OrdemFaturacaoController::class, 'update']);
        Route::patch('/', [OrdemFaturacaoController::class, 'update']);
        Route::delete('/', [OrdemFaturacaoController::class, 'destroy']);
        
        // Rota específica para gerar fatura
        Route::post('/faturar', [OrdemFaturacaoController::class, 'faturar']);
    });
});

Route::prefix('notas-fiscais')->group(function () {
    Route::get('/', [NotaFiscalController::class, 'index']);
    Route::post('/', [NotaFiscalController::class, 'store']);
    
    Route::prefix('{nota}')->group(function () {
        Route::get('/', [NotaFiscalController::class, 'show']);
        Route::put('/', [NotaFiscalController::class, 'update']);
        Route::patch('/', [NotaFiscalController::class, 'update']);
        Route::delete('/', [NotaFiscalController::class, 'destroy']);
    });
});