<?php
// routes/ordens/disponibilidade.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OrdemDisponibilidadeController;

Route::prefix('ordens')->group(function () {
    // Rota para verificar viabilidade
    Route::get('/{id}/check-viabilidade', [OrdemDisponibilidadeController::class, 'checkViabilidade']);
    
    // Containers
    Route::get('/{id}/containers-disponiveis', [OrdemDisponibilidadeController::class, 'getContainersDisponiveis']);
    Route::put('/containers/{containerId}/status', [OrdemDisponibilidadeController::class, 'updateContainerStatus']);
    
    // Break Bulk
    Route::get('/{id}/break-bulk-disponivel', [OrdemDisponibilidadeController::class, 'getBreakBulkDisponivel']);
    Route::put('/break-bulk/{breakBulkId}/consumir', [OrdemDisponibilidadeController::class, 'consumirBreakBulk']);
});