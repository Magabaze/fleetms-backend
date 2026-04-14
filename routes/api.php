<?php
// routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CadastroController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\PrintController;
use App\Http\Controllers\Api\DriverExpenseController;
use App\Http\Controllers\Api\PublicTrackingController;
use App\Http\Controllers\Api\OrdemDisponibilidadeController;
use App\Http\Controllers\Api\DespesasPrintController;
use App\Http\Controllers\Api\RelatorioExportController;
use App\Http\Controllers\Api\FaturacaoPrintController;
use App\Http\Controllers\Api\CombustivelPrintController;
use App\Http\Controllers\Api\UserManagementController;
use App\Http\Controllers\Api\BonusPrintController;

// ============================================
// ROTAS PÚBLICAS (SEM AUTENTICAÇÃO)
// ============================================
Route::get('/health', [AuthController::class, 'health']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register-empresa', [CadastroController::class, 'register']);
Route::get('/public/tracking/{code}', [PublicTrackingController::class, 'trackByCode'])->where('code', '.*');

// ============================================
// ROTAS DE IMPRESSÃO (COM TOKEN NA URL) - FORA DO GRUPO DE AUTENTICAÇÃO
// ============================================

// Viagens - Manifesto
Route::get('/viagens/{id}/print', [PrintController::class, 'generateManifest']);

// Viagens - Reforço de Valores (Despesas)
Route::get('/viagens/{viagemId}/print-despesas', [DespesasPrintController::class, 'printDespesas']);

// Faturação
Route::prefix('faturacao/print')->group(function () {
    Route::get('/nota/{id}', [FaturacaoPrintController::class, 'printNota']);
    Route::get('/ordem/{id}', [FaturacaoPrintController::class, 'printOrdem']);
});

// Combustível
Route::prefix('combustivel/print')->group(function () {
    Route::get('/abastecimento-externo/{id}', [CombustivelPrintController::class, 'printAbastecimentoExterno']);
    Route::get('/abastecimento-interno/{id}', [CombustivelPrintController::class, 'printAbastecimentoInterno']);
    Route::get('/pedido-compra/{id}', [CombustivelPrintController::class, 'printPedidoCompra']);
});

// Manutenção
Route::prefix('manutencao/print')->group(function () {
    Route::get('/ordem-trabalho/{id}', [App\Http\Controllers\Api\Manutencao\ManutencaoPrintController::class, 'printOrdemTrabalho']);
    Route::get('/avaria/{id}', [App\Http\Controllers\Api\Manutencao\ManutencaoPrintController::class, 'printAvaria']);
    Route::get('/plano-preventivo/{id}', [App\Http\Controllers\Api\Manutencao\ManutencaoPrintController::class, 'printPlanoPreventivo']);
});

// Bónus e Pagamentos
Route::prefix('bonus/print')->group(function () {
    Route::get('/pagamento/{id}', [BonusPrintController::class, 'printPagamento']);
    Route::get('/extrato', [BonusPrintController::class, 'printExtrato']);
});

// Caixa / Justificativos
Route::prefix('caixa/print')->group(function () {
    Route::get('/justificativo/{viagemId}', [App\Http\Controllers\Api\CaixaPrintController::class, 'printJustificativo']);
    Route::get('/resumo-despesas/{viagemId}', [App\Http\Controllers\Api\CaixaPrintController::class, 'printResumoDespesas']);
});

// ============================================
// ROTAS PROTEGIDAS (COM AUTENTICAÇÃO SANCTUM)
// ============================================
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // ============================================
    // MÓDULO DE USUÁRIOS
    // ============================================
    Route::prefix('usuarios')->group(function () {
        
        // Rotas ESPECÍFICAS (sem parâmetros)
        Route::get('/meu-perfil', [UserManagementController::class, 'meuPerfil']);
        Route::put('/meu-perfil', [UserManagementController::class, 'atualizarMeuPerfil']);
        Route::put('/minha-senha', [UserManagementController::class, 'atualizarMinhaSenha']);
        Route::get('/minhas-permissoes', [UserManagementController::class, 'minhasPermissoes']);
        Route::get('/estatisticas', [UserManagementController::class, 'estatisticas']);
        
        // Rotas de roles
        Route::prefix('roles')->group(function () {
            Route::get('/', [UserManagementController::class, 'getRoles']);
            Route::post('/', [UserManagementController::class, 'storeRole']);
            Route::get('/{roleId}/permissoes', [UserManagementController::class, 'getRolePermissions']);
            Route::put('/{roleId}/permissoes', [UserManagementController::class, 'updateRolePermissions']);
            Route::put('/{id}', [UserManagementController::class, 'updateRole']);
            Route::delete('/{id}', [UserManagementController::class, 'destroyRole']);
        });
        
        // Rotas de permissões
        Route::get('/permissoes', [UserManagementController::class, 'getPermissions']);
        
        // Rotas com parâmetros
        Route::get('/{id}', [UserManagementController::class, 'show']);
        Route::put('/{id}', [UserManagementController::class, 'update']);
        Route::delete('/{id}', [UserManagementController::class, 'destroy']);
        Route::patch('/{id}/toggle-status', [UserManagementController::class, 'toggleStatus']);
        
        // Listagem e criação
        Route::get('/', [UserManagementController::class, 'index']);
        Route::post('/', [UserManagementController::class, 'store']);
    });

    // ============================================
    // BÓNUS (inclui regras, bónus, descontos, carteiras) - SEM ROTAS DE PRINT
    // ============================================
    Route::prefix('bonus')->group(function () {
        require __DIR__ . '/bonus/bonus.php';
    });

    // ============================================
    // UPLOAD
    // ============================================
    Route::prefix('upload')->group(function () {
        Route::post('/logo', [FileUploadController::class, 'uploadLogo']);
        Route::post('/motorista-foto', [FileUploadController::class, 'uploadMotoristaFoto']);
        Route::post('/documento', [FileUploadController::class, 'uploadDocumento']);
        Route::delete('/arquivo', [FileUploadController::class, 'deleteFile']);
    });

    // ============================================
    // DESPESAS DE MOTORISTAS
    // ============================================
    Route::prefix('driver-expenses')->group(function () {
        Route::get('/test', [DriverExpenseController::class, 'test']);
        Route::get('/tipos-despesa', [DriverExpenseController::class, 'tiposDespesa']);
        Route::post('/tipos-despesa', [DriverExpenseController::class, 'criarTipoDespesa']);
        Route::post('/aprovar-lote', [DriverExpenseController::class, 'aprovarLote']);
        Route::post('/cancelar-lote', [DriverExpenseController::class, 'cancelarLote']);
        Route::post('/por-periodo', [DriverExpenseController::class, 'buscarPorPeriodo']);
        Route::get('/exportar-pdf/{viagemId}', [DriverExpenseController::class, 'exportarPdf']);
        Route::get('/{id}', [DriverExpenseController::class, 'buscarPorId']);
    });

    // ============================================
    // VIAGENS - ROTAS PROTEGIDAS (CRUD)
    // ============================================
    Route::prefix('viagens')->group(function () {
        // Estas rotas NÃO incluem /print, apenas CRUD normal
        Route::get('/recursos', [App\Http\Controllers\Api\ViagemController::class, 'recursos']);
        Route::get('/para-faturar', [App\Http\Controllers\Api\ViagemFaturacaoController::class, 'paraFaturar']);
        Route::get('/motorista/{motorista}/status', [App\Http\Controllers\Api\ViagemController::class, 'verificarStatusMotorista']);
        Route::get('/camiao/{matricula}/status', [App\Http\Controllers\Api\ViagemController::class, 'verificarStatusCamiao']);
        Route::get('/trela/{matricula}/status', [App\Http\Controllers\Api\ViagemController::class, 'verificarStatusTrela']);
        Route::get('/', [App\Http\Controllers\Api\ViagemController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\ViagemController::class, 'store']);
        
        Route::prefix('{viagem}')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\ViagemController::class, 'show']);
            Route::put('/', [App\Http\Controllers\Api\ViagemController::class, 'update']);
            Route::delete('/', [App\Http\Controllers\Api\ViagemController::class, 'destroy']);
            Route::patch('/tracking', [App\Http\Controllers\Api\ViagemController::class, 'atualizarTracking']);
            Route::put('/fechar', [App\Http\Controllers\Api\ViagemController::class, 'fecharViagem']);
            Route::post('/nova-leg', [App\Http\Controllers\Api\ViagemController::class, 'adicionarLeg']);
            Route::put('/alterar-destino', [App\Http\Controllers\Api\ViagemController::class, 'alterarDestino']);
            Route::put('/alterar-ordem', [App\Http\Controllers\Api\ViagemController::class, 'alterarOrdem']);
            Route::put('/desfazer', [App\Http\Controllers\Api\ViagemController::class, 'desfazerViagem']);
            Route::put('/km-extra', [App\Http\Controllers\Api\ViagemController::class, 'adicionarKMExtra']);
        });

        Route::prefix('{viagemId}/despesas')->group(function () {
            Route::get('/', [DriverExpenseController::class, 'buscarPorViagem']);
            Route::post('/', [DriverExpenseController::class, 'criarParaViagem']);
            Route::post('/aprovar-todas', [DriverExpenseController::class, 'aprovarTodas']);
            Route::prefix('{id}')->group(function () {
                Route::put('/', [DriverExpenseController::class, 'atualizar']);
                Route::delete('/', [DriverExpenseController::class, 'deletar']);
            });
        });
    });

    // ============================================
    // DISPONIBILIDADE DE ORDENS
    // ============================================
    Route::prefix('ordens')->group(function () {
        Route::get('/{id}/containers-disponiveis', [OrdemDisponibilidadeController::class, 'getContainersDisponiveis']);
        Route::post('/containers/{containerId}/marcar-usado', [OrdemDisponibilidadeController::class, 'marcarContainerComoUsado']);
        Route::get('/containers/{containerId}/status', [OrdemDisponibilidadeController::class, 'getContainerStatus']);
        Route::get('/{id}/break-bulk-disponivel', [OrdemDisponibilidadeController::class, 'getBreakBulkDisponivel']);
        Route::post('/break-bulk/{breakBulkId}/consumir-viagem', [OrdemDisponibilidadeController::class, 'consumirBreakBulkParaViagem']);
        Route::get('/break-bulk/{breakBulkId}/status', [OrdemDisponibilidadeController::class, 'getBreakBulkStatus']);
        Route::get('/{id}/check-viabilidade', [OrdemDisponibilidadeController::class, 'checkViabilidade']);
    });

    // ============================================
    // ROTAS LEGADAS (despesas-motoristas)
    // ============================================
    Route::prefix('despesas-motoristas')->group(function () {
        Route::post('/aprovar-lote', [DriverExpenseController::class, 'aprovarLote']);
        Route::post('/cancelar-lote', [DriverExpenseController::class, 'cancelarLote']);
        Route::post('/por-periodo', [DriverExpenseController::class, 'buscarPorPeriodo']);
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
    // MANUTENÇÃO
    // ============================================
    Route::prefix('manutencao')->group(function () {
        $manutencaoFiles = [
            'ordens-trabalho.php',
            'avarias.php',
            'fornecedores.php',      
            'orcamentos.php',        
            'externa.php',           
            'socorro.php',       
            'pecas.php',
            'preventiva.php',
            'inspecoes.php',
        ];
        
        foreach ($manutencaoFiles as $file) {
            $path = __DIR__ . '/manutencao/' . $file;
            if (file_exists($path)) {
                require $path;
            }
        }
    });

    // ============================================
    // COMBUSTÍVEL
    // ============================================
    $combustivelFiles = [
        'combustivel/combustivel.php',
        'combustivel/tanques.php',
    ];
    
    foreach ($combustivelFiles as $file) {
        $path = __DIR__ . '/' . $file;
        if (file_exists($path)) {
            require $path;
        }
    }

    // ============================================
    // FATURAÇÃO
    // ============================================
    Route::prefix('ordens-faturacao')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\OrdemFaturacaoController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\OrdemFaturacaoController::class, 'store']);
        Route::prefix('{id}')->group(function () {
            Route::post('/processar', [App\Http\Controllers\Api\OrdemFaturacaoController::class, 'marcarProcessado']);
            Route::put('/', [App\Http\Controllers\Api\OrdemFaturacaoController::class, 'update']);
            Route::delete('/', [App\Http\Controllers\Api\OrdemFaturacaoController::class, 'destroy']);
        });
    });

    Route::prefix('notas-fiscais')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\NotaFiscalController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\NotaFiscalController::class, 'store']);
        Route::prefix('{id}')->group(function () {
            Route::put('/', [App\Http\Controllers\Api\NotaFiscalController::class, 'update']);
            Route::delete('/', [App\Http\Controllers\Api\NotaFiscalController::class, 'destroy']);
        });
    });

    // ============================================
    // RELATÓRIOS
    // ============================================
    Route::prefix('relatorios')->group(function () {
        Route::post('/exportar/viagens', [RelatorioExportController::class, 'exportarViagens']);
        Route::post('/exportar/financeiro', [RelatorioExportController::class, 'exportarFinanceiro']);
        Route::post('/exportar/motoristas', [RelatorioExportController::class, 'exportarMotoristas']);
        Route::post('/exportar/frota', [RelatorioExportController::class, 'exportarFrota']);
        Route::post('/exportar/manutencao', [RelatorioExportController::class, 'exportarManutencao']);
        Route::post('/exportar/combustivel', [RelatorioExportController::class, 'exportarCombustivel']);
    });

    // ============================================
    // OUTROS MÓDULOS (via include)
    // ============================================
    $arquivosParaIncluir = [
        '/configuracoes/configuracoes.php',
        '/clientes/clientes.php',
        '/agentes/agentes.php',
        '/cargas/cargas.php',
        '/distancias/distancias.php',
        '/rates/rates.php',
        '/ordens/ordens.php',
        '/despesas/despesas.php',
        '/despesas/tipos.php',
        '/motoristas/motoristas.php',
        '/camioes/camioes.php',
        '/empresa/empresa.php',
        '/reportes/reportes.php',
        '/dashboard/dashboard.php',
        '/caixa/caixa.php',
    ];

    foreach ($arquivosParaIncluir as $arquivo) {
        $caminho = __DIR__ . $arquivo;
        if (file_exists($caminho)) {
            require $caminho;
        }
    }
});

// ============================================
// FALLBACK (404)
// ============================================
Route::fallback(function () {
    return response()->json([
        'success'     => false,
        'error'       => 'Rota não encontrada',
        'timestamp'   => now()->toISOString(),
        'suggestions' => [
            'Verifique se a rota está correta',
            'Verifique se o método HTTP está correto',
            'Certifique-se de estar autenticado para rotas protegidas',
        ],
    ], 404);
});