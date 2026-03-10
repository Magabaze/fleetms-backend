<?php
// routes/api/configuracoes/configuracoes.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ConfiguracaoController;
use App\Http\Controllers\Api\EmpresaUploadController;

Route::prefix('configuracoes')->group(function () {
    // =============== PERFIL ===============
    Route::get('/perfil', [ConfiguracaoController::class, 'getPerfil']);
    Route::put('/perfil', [ConfiguracaoController::class, 'updatePerfil']);
    
    // =============== DADOS DA EMPRESA ===============
    Route::get('/empresa', [ConfiguracaoController::class, 'getEmpresa']);
    Route::put('/empresa', [ConfiguracaoController::class, 'updateEmpresa']);
    
    // =============== UPLOAD DE LOGO ===============
    // Nota: Como estamos dentro do prefixo 'configuracoes', 
    // esta rota responde em: /api/configuracoes/empresa/upload-logo
    Route::post('/empresa/upload-logo', [EmpresaUploadController::class, 'uploadLogo']);
    
    Route::delete('/empresa/remove-logo', [EmpresaUploadController::class, 'removeLogo']);
});