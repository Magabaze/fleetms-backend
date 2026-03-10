<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OrdemDisponibilidadeController;

Route::prefix('ordens')->group(function () {
    // Verificação de viabilidade (já existe e está correto)
    Route::get('/{id}/check-viabilidade', [OrdemDisponibilidadeController::class, 'checkViabilidade']);
    
    // Containers disponíveis (já correto)
    Route::get('/{id}/containers-disponiveis', [OrdemDisponibilidadeController::class, 'getContainersDisponiveis']);
    
    // Atualizar status do container (alinhado com o frontend)
    Route::put('/containers/{containerId}/status', [OrdemDisponibilidadeController::class, 'updateContainerStatus']);
    // Opcional: se quiser suportar PATCH também (muito comum em APIs REST)
    Route::patch('/containers/{containerId}/status', [OrdemDisponibilidadeController::class, 'updateContainerStatus']);
    
    // Break bulk disponíveis (já correto)
    Route::get('/{id}/break-bulk-disponivel', [OrdemDisponibilidadeController::class, 'getBreakBulkDisponivel']);
    
    // Consumir break bulk (alinhado com o frontend)
    Route::put('/break-bulk/{breakBulkId}/consumir', [OrdemDisponibilidadeController::class, 'consumirBreakBulk']);
    // Opcional: suporte a PATCH
    Route::patch('/break-bulk/{breakBulkId}/consumir', [OrdemDisponibilidadeController::class, 'consumirBreakBulk']);
});