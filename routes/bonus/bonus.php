<?php
// routes/bonus/bonus.php
//
// Incluído pelo api.php dentro de:
//   Route::middleware('auth:sanctum')->group(function () {
//       Route::prefix('bonus')->group(function () {
//           require __DIR__ . '/bonus/bonus.php';
//       });
//   });
//
// ⚠️  REGRA CRÍTICA: rotas fixas ANTES de /{id}
// ⚠️  ROTAS DE PRINT ESTÃO NO API.PHP FORA DO GRUPO DE AUTENTICAÇÃO

use App\Http\Controllers\Api\RegraBonusController;
use App\Http\Controllers\Api\BonusController;
use App\Http\Controllers\Api\DescontoController;
use App\Http\Controllers\Api\PagamentoController;
use App\Http\Controllers\Api\BonusRelatorioController;
use App\Http\Controllers\Api\CarteiraController;

// ─── CARTEIRA DO MOTORISTA → /api/bonus/carteiras ────────
Route::prefix('carteiras')->group(function () {
    Route::get('/',           [CarteiraController::class, 'index']);      // Listar todas
    Route::get('/resumo',     [CarteiraController::class, 'resumo']);     // Resumo geral
    Route::get('/extrato',    [CarteiraController::class, 'extrato']);    // Extrato por período
    Route::post('/pagar',     [CarteiraController::class, 'pagar']);      // Registrar pagamento
    Route::get('/verificar-dados', [CarteiraController::class, 'verificarDados']); // Verificar dados
    Route::post('/inicializar-todas', [CarteiraController::class, 'inicializarTodas']); // Inicializar todas
    Route::get('/{motorista}', [CarteiraController::class, 'show']);      // Carteira específica
});

// ─── REGRAS DE BÓNUS  →  /api/bonus/regras ───────────────────────
Route::prefix('regras')->group(function () {
    Route::get('/',        [RegraBonusController::class, 'index']);
    Route::post('/',       [RegraBonusController::class, 'store']);
    Route::get('/{id}',    [RegraBonusController::class, 'show']);
    Route::put('/{id}',    [RegraBonusController::class, 'update']);
    Route::delete('/{id}', [RegraBonusController::class, 'destroy']);
});

// ─── BÓNUS GERADOS  →  /api/bonus/bonus ──────────────────────────
Route::prefix('bonus')->group(function () {

    Route::get('/',    [BonusController::class, 'index']);
    Route::post('/',   [BonusController::class, 'store']);

    // ✅ Rotas fixas ANTES de /{id}
    Route::post('/calcular',      [BonusController::class, 'calcularBonus']);
    Route::post('/aprovar-lote',  [BonusController::class, 'aprovarLote']);
    Route::post('/rejeitar-lote', [BonusController::class, 'rejeitarLote']);
    Route::get('/diagnostico',    [BonusController::class, 'diagnosticoSimples']);

    // ⬇ parâmetro dinâmico sempre por último
    Route::get('/{id}',    [BonusController::class, 'show']);
    Route::delete('/{id}', [BonusController::class, 'destroy']);
});

// ─── DESCONTOS  →  /api/bonus/descontos ──────────────────────────
Route::prefix('descontos')->group(function () {
    Route::get('/',        [DescontoController::class, 'index']);
    Route::post('/',       [DescontoController::class, 'store']);
    Route::get('/{id}',    [DescontoController::class, 'show']);
    Route::put('/{id}',    [DescontoController::class, 'update']);
    Route::delete('/{id}', [DescontoController::class, 'destroy']);
});

// ─── PAGAMENTOS  →  /api/bonus/pagamentos ────────────────────────
Route::prefix('pagamentos')->group(function () {
    Route::get('/pendentes',  [PagamentoController::class, 'pendentes']);
    Route::post('/registrar', [PagamentoController::class, 'registrar']);
    Route::get('/historico',  [PagamentoController::class, 'historico']);
    Route::get('/{id}',       [PagamentoController::class, 'show']);
});

// ─── RELATÓRIOS  →  /api/bonus/relatorios ────────────────────────
Route::prefix('relatorios')->group(function () {
    Route::get('/resumo-mensal',  [BonusRelatorioController::class, 'resumoMensal']);
    Route::get('/por-motorista',  [BonusRelatorioController::class, 'porMotorista']);
    Route::get('/exportar-excel', [BonusRelatorioController::class, 'exportarExcel']);
});