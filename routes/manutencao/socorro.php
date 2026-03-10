<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Manutencao\SocorroController;

Route::prefix('socorro')->group(function () {
    Route::get('/', [SocorroController::class, 'index']);
    Route::post('/', [SocorroController::class, 'store']);
    
    Route::prefix('{socorro}')->group(function () {
        Route::get('/', [SocorroController::class, 'show']);
        Route::put('/', [SocorroController::class, 'update']);
        Route::patch('/', [SocorroController::class, 'update']);
        Route::delete('/', [SocorroController::class, 'destroy']);
    });
    
    Route::post('/{id}/iniciar-atendimento', [SocorroController::class, 'iniciarAtendimento']);
    Route::post('/{id}/concluir-atendimento', [SocorroController::class, 'concluirAtendimento']);
});