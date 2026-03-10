<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Manutencao\FornecedorManutencaoController;

Route::prefix('fornecedores')->group(function () {
    Route::get('/', [FornecedorManutencaoController::class, 'index']);
    Route::post('/', [FornecedorManutencaoController::class, 'store']);
    
    Route::prefix('{fornecedor}')->group(function () {
        Route::get('/', [FornecedorManutencaoController::class, 'show']);
        Route::put('/', [FornecedorManutencaoController::class, 'update']);
        Route::patch('/', [FornecedorManutencaoController::class, 'update']);
        Route::delete('/', [FornecedorManutencaoController::class, 'destroy']);
    });
});