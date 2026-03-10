<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DriverExpenseController;

// ============================================
// ROTAS DE DESPESAS DE MOTORISTAS (DRIVER EXPENSES)
// ============================================
// ESTE ARQUIVO ESTÁ SENDO INCLUÍDO NO API.PHP
// NÃO DUPLICAR ROTAS QUE JÁ EXISTEM NO ARQUIVO PRINCIPAL

Route::prefix('driver-expenses')->group(function () {
    // Apenas rotas específicas que não existem no api.php
    Route::get('/test', [DriverExpenseController::class, 'test']);
    Route::get('/tipos-despesa', [DriverExpenseController::class, 'tiposDespesa']);
    Route::post('/tipos-despesa', [DriverExpenseController::class, 'criarTipoDespesa']);
});

// ✅ NÃO DEFINIR ROTAS DE PRINT AQUI - ELAS JÁ ESTÃO NO API.PHP
// ❌ Route::post('{viagemId}/print-despesas', ...) - REMOVIDO
// ❌ Route::get('{viagemId}/print-despesas', ...) - REMOVIDO

// Compatibilidade com nome antigo
Route::prefix('despesas-motoristas')->group(function () {
    Route::get('/', [DriverExpenseController::class, 'buscarPreCadastradas']);
    Route::post('/aprovar-lote', [DriverExpenseController::class, 'aprovarLote']);
    Route::post('/cancelar-lote', [DriverExpenseController::class, 'cancelarLote']);
});