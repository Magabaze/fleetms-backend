<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Manutencao\InspecaoController;

/*
|--------------------------------------------------------------------------
| Rotas de Inspeções
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('inspecoes')->group(function () {

        // Listar inspeções
        Route::get('/', [InspecaoController::class, 'index']);

        // Criar nova inspeção
        Route::post('/', [InspecaoController::class, 'store']);

        // Rotas com ID
        Route::prefix('{id}')->group(function () {

            // Ver uma inspeção
            Route::get('/', [InspecaoController::class, 'show']);

            // Atualizar inspeção
            Route::put('/', [InspecaoController::class, 'update']);
            Route::patch('/', [InspecaoController::class, 'update']);

            // Excluir inspeção
            Route::delete('/', [InspecaoController::class, 'destroy']);

            // Renovar inspeção
            Route::post('/renovar', [InspecaoController::class, 'renovar']);

        });

    });

});
