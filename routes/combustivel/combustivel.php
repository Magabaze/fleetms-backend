<?php
// routes/api/combustivel.php - VERSÃO COMPLETA CORRIGIDA COM TANQUES

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Combustivel\PedidoCompraController;
use App\Http\Controllers\Api\Combustivel\AbastecimentoInternoController;
use App\Http\Controllers\Api\Combustivel\AbastecimentoExternoController;
use App\Http\Controllers\Api\Combustivel\FornecedorController;
use App\Http\Controllers\Api\Combustivel\TanqueController; // 👈 IMPORT ADICIONADO
use Illuminate\Http\Request;

Route::prefix('combustivel')->group(function () {
    
    // ============================================
    // ✅ FORNECEDORES E POSTOS
    // ============================================
    Route::prefix('fornecedores')->group(function () {
        Route::get('/', [FornecedorController::class, 'index']);
        Route::post('/', [FornecedorController::class, 'store']);
        Route::get('/dropdown', [FornecedorController::class, 'dropdownFornecedores']);
        
        Route::prefix('{id}')->group(function () {
            Route::get('/', [FornecedorController::class, 'show']);
            Route::put('/', [FornecedorController::class, 'update']);
            Route::delete('/', [FornecedorController::class, 'destroy']);
        });
    });
    
    // ============================================
    // ✅ PEDIDOS DE COMPRA
    // ============================================
    Route::prefix('pedidos-compra')->group(function () {
        Route::get('/', [PedidoCompraController::class, 'index']);
        Route::post('/', [PedidoCompraController::class, 'store']);
        
        Route::prefix('{id}')->group(function () {
            Route::get('/', [PedidoCompraController::class, 'show']);
            Route::put('/', [PedidoCompraController::class, 'update']);
            Route::delete('/', [PedidoCompraController::class, 'destroy']);
            Route::put('/aprovar', [PedidoCompraController::class, 'aprovar']);
            Route::put('/rejeitar', [PedidoCompraController::class, 'rejeitar']);
            Route::put('/entregar', [PedidoCompraController::class, 'entregar']);
            Route::put('/cancelar', [PedidoCompraController::class, 'cancelar']);
        });
    });
    
    // ============================================
    // ✅ ABASTECIMENTOS INTERNOS - COMPLETO CORRIGIDO
    // ============================================
    Route::prefix('abastecimentos-internos')->group(function () {
        Route::get('/', [AbastecimentoInternoController::class, 'index']);
        Route::post('/', [AbastecimentoInternoController::class, 'store']);
        
        Route::prefix('{id}')->group(function () {
            Route::get('/', [AbastecimentoInternoController::class, 'show']);
            Route::put('/', [AbastecimentoInternoController::class, 'update']);
            Route::delete('/', [AbastecimentoInternoController::class, 'destroy']);
            
            // ✅ ENDPOINTS ESPECÍFICOS - CORRIGIDOS E COMPLETOS
            Route::put('/aprovar', [AbastecimentoInternoController::class, 'aprovar']);
            Route::put('/cancelar', [AbastecimentoInternoController::class, 'cancelar']);
            Route::put('/marcar-realizado', [AbastecimentoInternoController::class, 'marcarRealizado']);
        });
    });
    
    // ============================================
    // ✅ ABASTECIMENTOS EXTERNOS
    // ============================================
    Route::prefix('abastecimentos-externos')->group(function () {
        Route::get('/', [AbastecimentoExternoController::class, 'index']);
        Route::post('/', [AbastecimentoExternoController::class, 'store']);
        
        Route::prefix('{id}')->group(function () {
            Route::get('/', [AbastecimentoExternoController::class, 'show']);
            Route::put('/', [AbastecimentoExternoController::class, 'update']);
            Route::delete('/', [AbastecimentoExternoController::class, 'destroy']);
            Route::put('/aprovar', [AbastecimentoExternoController::class, 'aprovar']);
            Route::put('/rejeitar', [AbastecimentoExternoController::class, 'rejeitar']);
            Route::put('/pagar', [AbastecimentoExternoController::class, 'pagar']);
            Route::put('/cancelar', [AbastecimentoExternoController::class, 'cancelar']);
        });
    });

    // ============================================
    // ✅ TANQUES - NOVA SEÇÃO ADICIONADA
    // ============================================
    Route::prefix('tanques')->group(function () {
        // Listar tanques com busca/paginação
        Route::get('/', [TanqueController::class, 'index']);
        
        // Criar novo tanque
        Route::post('/', [TanqueController::class, 'store']);
        
        // Recursos (status, unidades de medida)
        Route::get('/recursos', [TanqueController::class, 'recursos']);
        
        // Rotas que precisam de ID
        Route::prefix('{id}')->group(function () {
            // Ver tanque específico
            Route::get('/', [TanqueController::class, 'show']);
            
            // Atualizar tanque
            Route::put('/', [TanqueController::class, 'update']);
            Route::patch('/', [TanqueController::class, 'update']);
            
            // Excluir tanque
            Route::delete('/', [TanqueController::class, 'destroy']);
            
            // Ações específicas
            Route::put('/ativar', [TanqueController::class, 'ativar']);
            Route::put('/desativar', [TanqueController::class, 'desativar']);
            Route::put('/reabastecer', [TanqueController::class, 'reabastecer']);
        });
    });
    
    // ============================================
    // ✅ RELATÓRIOS E ESTATÍSTICAS
    // ============================================
    Route::prefix('relatorios')->group(function () {
        Route::get('/resumo', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_pedidos' => \App\Models\Combustivel\PedidoCompra::count(),
                    'total_abastecimentos_internos' => \App\Models\Combustivel\AbastecimentoInterno::count(),
                    'total_abastecimentos_externos' => \App\Models\Combustivel\AbastecimentoExterno::count(),
                    'total_fornecedores' => \App\Models\Combustivel\FornecedorCombustivel::count(),
                    'total_postos' => \App\Models\Combustivel\PostoCombustivel::count(),
                    'total_tanques' => \App\Models\Combustivel\Tanque::count(), // 👈 ADICIONADO
                ]
            ]);
        });
        
        Route::post('/consumo-por-periodo', function (Request $request) {
            return response()->json(['success' => true, 'message' => 'Em implementação']);
        });
        
        Route::post('/custos-por-veiculo', function (Request $request) {
            return response()->json(['success' => true, 'message' => 'Em implementação']);
        });
    });
});