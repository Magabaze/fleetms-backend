<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Manutencao\OrdemTrabalhoController;

Route::prefix('ordens-trabalho')->group(function () {
    Route::get('/', [OrdemTrabalhoController::class, 'index']);
    Route::post('/', [OrdemTrabalhoController::class, 'store']);
    
    Route::prefix('{ordem}')->group(function () {
        Route::get('/', [OrdemTrabalhoController::class, 'show']);
        Route::put('/', [OrdemTrabalhoController::class, 'update']);
        Route::patch('/', [OrdemTrabalhoController::class, 'update']);
        Route::delete('/', [OrdemTrabalhoController::class, 'destroy']);
    });
});