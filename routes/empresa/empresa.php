<?php
// routes/api/empresa/empresa.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ConfiguracaoController;
use App\Http\Controllers\Api\EmpresaUploadController;

Route::prefix('configuracoes')->group(function () {
    // Perfil
    Route::get('/perfil', [ConfiguracaoController::class, 'getPerfil']);
    Route::put('/perfil', [ConfiguracaoController::class, 'updatePerfil']);
    
    // Empresa (apenas admin)
    Route::get('/empresa', [ConfiguracaoController::class, 'getEmpresa']);
    Route::put('/empresa', [ConfiguracaoController::class, 'updateEmpresa']);
    
    // ✅ Uploads específicos da empresa
    Route::post('/empresa/upload-logo', [EmpresaUploadController::class, 'uploadLogo']);
    Route::delete('/empresa/remove-logo', [EmpresaUploadController::class, 'removeLogo']);
});