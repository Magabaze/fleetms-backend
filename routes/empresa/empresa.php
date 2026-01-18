<?php
// routes/api/configuracoes/configuracoes.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ConfiguracaoController;

Route::prefix('configuracoes')->group(function () {
    // Perfil do usuário
    Route::get('/perfil', [ConfiguracaoController::class, 'getPerfil']);
    Route::put('/perfil', [ConfiguracaoController::class, 'updatePerfil']);
    
    // Dados da empresa (apenas admin)
    Route::get('/empresa', [ConfiguracaoController::class, 'getEmpresa']);
    Route::put('/empresa', [ConfiguracaoController::class, 'updateEmpresa']);
});