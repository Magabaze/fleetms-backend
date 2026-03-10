<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Manutencao\PlanoPreventivoController;

/*
|--------------------------------------------------------------------------
| Rotas de Plano Preventivo
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('preventiva')->group(function () {

        // Listar planos
        Route::get('/', [PlanoPreventivoController::class, 'index']);

        // Criar plano
        Route::post('/', [PlanoPreventivoController::class, 'store']);

        // Rotas com ID
        Route::prefix('{id}')->group(function () {

            // Ver plano
            Route::get('/', [PlanoPreventivoController::class, 'show']);

            // Atualizar plano
            Route::put('/', [PlanoPreventivoController::class, 'update']);
            Route::patch('/', [PlanoPreventivoController::class, 'update']);

            // Excluir plano
            Route::delete('/', [PlanoPreventivoController::class, 'destroy']);

        });

    });

});
