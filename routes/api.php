<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PrintController;
use App\Http\Controllers\Api\DriverExpenseController;

// ============================================
// ROTAS PÚBLICAS
// ============================================
Route::get('/health', [AuthController::class, 'health']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// ============================================
// ✅ ROTA DE PRINT FORA DO MIDDLEWARE
// ============================================
Route::get('/viagens/{id}/print', [PrintController::class, 'generateManifest']);

// ============================================
// ROTAS PROTEGIDAS
// ============================================
Route::middleware('auth:sanctum')->group(function () {
    
    // Autenticação do usuário
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    
    // ============================================
    // ✅ DRIVER EXPENSES (DESPESAS DE MOTORISTAS)
    // ============================================
    Route::prefix('driver-expenses')->group(function () {
        // Test endpoint
        Route::get('/test', [DriverExpenseController::class, 'test']);
        
        // Tipos de despesa
        Route::get('/tipos-despesa', [DriverExpenseController::class, 'tiposDespesa']);
        Route::post('/tipos-despesa', [DriverExpenseController::class, 'criarTipoDespesa']);
        
        // Operações em lote
        Route::post('/aprovar-lote', [DriverExpenseController::class, 'aprovarLote']);
        Route::post('/cancelar-lote', [DriverExpenseController::class, 'cancelarLote']);
        
        // Busca por período
        Route::post('/por-periodo', [DriverExpenseController::class, 'buscarPorPeriodo']);
        
        // Exportar PDF
        Route::get('/exportar-pdf/{viagemId}', [DriverExpenseController::class, 'exportarPdf']);
        
        // Buscar despesa específica
        Route::get('/{id}', [DriverExpenseController::class, 'buscarPorId']);
    });

    // ============================================
    // ✅ DESPESAS POR VIAGEM (Compatível com frontend)
    // ============================================
    Route::prefix('viagens')->group(function () {
        Route::prefix('{viagemId}/despesas')->group(function () {
            // Listar despesas da viagem
            Route::get('/', [DriverExpenseController::class, 'buscarPorViagem']);
            
            // Criar nova despesa
            Route::post('/', [DriverExpenseController::class, 'criarParaViagem']);
            
            // Aprovar todas as despesas da viagem
            Route::post('/aprovar-todas', [DriverExpenseController::class, 'aprovarTodas']);
            
            // Operações em despesa específica
            Route::prefix('{id}')->group(function () {
                // Atualizar despesa
                Route::put('/', [DriverExpenseController::class, 'atualizar']);
                
                // Deletar despesa
                Route::delete('/', [DriverExpenseController::class, 'deletar']);
            });
        });
    });

    // ============================================
    // ✅ ROTAS COMPATÍVEIS (para manter compatibilidade)
    // ============================================
    Route::prefix('despesas-motoristas')->group(function () {
        Route::post('/aprovar-lote', [DriverExpenseController::class, 'aprovarLote']);
        Route::post('/cancelar-lote', [DriverExpenseController::class, 'cancelarLote']);
        Route::post('/por-periodo', [DriverExpenseController::class, 'buscarPorPeriodo']);
        
        // ✅ ADICIONADO: Rotas para despesas pré-cadastradas por rota
        Route::get('/', [App\Http\Controllers\Api\DespesaMotoristaController::class, 'index']);
        Route::get('/distancias-disponiveis', [App\Http\Controllers\Api\DespesaMotoristaController::class, 'distanciasDisponiveis']);
        Route::get('/tipos', [App\Http\Controllers\Api\DespesaMotoristaController::class, 'tiposDespesa']);
        Route::get('/estatisticas', [App\Http\Controllers\Api\DespesaMotoristaController::class, 'estatisticas']);
        Route::post('/despesas', [App\Http\Controllers\Api\DespesaMotoristaController::class, 'storeDespesa']);
        Route::put('/despesas/{id}', [App\Http\Controllers\Api\DespesaMotoristaController::class, 'updateDespesa']);
        Route::delete('/despesas/{id}', [App\Http\Controllers\Api\DespesaMotoristaController::class, 'destroyDespesa']);
        Route::get('/test', [App\Http\Controllers\Api\DespesaMotoristaController::class, 'test']);
    });

    // ============================================
    // OUTRAS ROTAS POR MÓDULO
    // ============================================
    
    // ✅ Configurações
    if (file_exists(__DIR__ . '/configuracoes/configuracoes.php')) {
        require __DIR__ . '/configuracoes/configuracoes.php';
    }
    
    // ✅ Gerenciamento de usuários
    if (file_exists(__DIR__ . '/usuarios/usuarios.php')) {
        require __DIR__ . '/usuarios/usuarios.php';
    }
    
    // ✅ Clientes
    if (file_exists(__DIR__ . '/clientes/clientes.php')) {
        require __DIR__ . '/clientes/clientes.php';
    }
    
    // ✅ Agentes
    if (file_exists(__DIR__ . '/agentes/agentes.php')) {
        require __DIR__ . '/agentes/agentes.php';
    }
    
    // ✅ Cargas
    if (file_exists(__DIR__ . '/cargas/cargas.php')) {
        require __DIR__ . '/cargas/cargas.php';
    }
    
    // ✅ Distâncias
    if (file_exists(__DIR__ . '/distancias/distancias.php')) {
        require __DIR__ . '/distancias/distancias.php';
    }
    
    // ✅ Rates
    if (file_exists(__DIR__ . '/rates/rates.php')) {
        require __DIR__ . '/rates/rates.php';
    }
    
    // ✅ Ordens
    if (file_exists(__DIR__ . '/ordens/ordens.php')) {
        require __DIR__ . '/ordens/ordens.php';
    }
    
    // ✅ Despesas Motoristas (se houver arquivo separado)
    if (file_exists(__DIR__ . '/despesas/despesas.php')) {
        require __DIR__ . '/despesas/despesas.php';
    }
    
    // ✅ Tipos de Despesa
    if (file_exists(__DIR__ . '/despesas/tipos.php')) {
        require __DIR__ . '/despesas/tipos.php';
    }
    
    // ✅ Motoristas
    if (file_exists(__DIR__ . '/motoristas/motoristas.php')) {
        require __DIR__ . '/motoristas/motoristas.php';
    }
    
    // ✅ Camiões e Trelas
    if (file_exists(__DIR__ . '/camioes/camioes.php')) {
        require __DIR__ . '/camioes/camioes.php';
    }
    
    // ✅ Viagens (CRUD principal)
    if (file_exists(__DIR__ . '/viagens/viagens.php')) {
        require __DIR__ . '/viagens/viagens.php';
    }
    
    // ============================================
    // ROTAS ESPECIAIS DE VALIDAÇÃO
    // ============================================
    
    // Status de motorista
    Route::get('/viagens/motorista/{motorista}/status', [App\Http\Controllers\Api\ViagemController::class, 'verificarStatusMotorista']);
    
    // Status de camião
    Route::get('/viagens/camiao/{matricula}/status', [App\Http\Controllers\Api\ViagemController::class, 'verificarStatusCamiao']);
    
    // Status de trela
    Route::get('/viagens/trela/{matricula}/status', [App\Http\Controllers\Api\ViagemController::class, 'verificarStatusTrela']);
    
    // Containers disponíveis para ordem
    Route::get('/ordens/{id}/containers-disponiveis', [App\Http\Controllers\Api\OrdemController::class, 'containersDisponiveis']);
    
    // Break bulk disponível para ordem
    Route::get('/ordens/{id}/break-bulk-disponivel', [App\Http\Controllers\Api\OrdemController::class, 'breakBulkDisponivel']);
    
    // Marcar container como usado
    Route::put('/containers/{container}/marcar-usado', [App\Http\Controllers\Api\OrdemController::class, 'marcarContainerComoUsado']);
    
    // ============================================
    // ✅ EMPRESA
    // ============================================
    if (file_exists(__DIR__ . '/empresa/empresa.php')) {
        require __DIR__ . '/empresa/empresa.php';
    }
    
    // ============================================
    // ✅ REPORTES E ESTATÍSTICAS
    // ============================================
    if (file_exists(__DIR__ . '/reportes/reportes.php')) {
        require __DIR__ . '/reportes/reportes.php';
    }
    
    // ============================================
    // ✅ DASHBOARD
    // ============================================
    if (file_exists(__DIR__ . '/dashboard/dashboard.php')) {
        require __DIR__ . '/dashboard/dashboard.php';
    }
    
});

// ============================================
// ROTA DE FALLBACK PARA ERRO 404
// ============================================
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'error' => 'Rota não encontrada',
        'timestamp' => now()->toISOString(),
        'suggestions' => [
            'Verifique se a rota está correta',
            'Verifique se o método HTTP está correto',
            'Certifique-se de estar autenticado para rotas protegidas'
        ]
    ], 404);
});