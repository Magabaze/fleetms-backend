<?php
// routes/api/configuracoes.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ConfiguracaoController;

Route::prefix('configuracoes')->group(function () {
    // Perfil
    Route::get('/perfil', [ConfiguracaoController::class, 'getPerfil']);
    Route::put('/perfil', [ConfiguracaoController::class, 'updatePerfil']);
    
    // Empresa (apenas admin)
    Route::get('/empresa', [ConfiguracaoController::class, 'getEmpresa']);
    Route::put('/empresa', [ConfiguracaoController::class, 'updateEmpresa']);
});