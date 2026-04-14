<?php
// app/Http/Controllers/Api/RelatorioExportController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exports\ViagensExport;
use App\Exports\FrotaExport;
use App\Exports\ManutencaoExport;
use App\Exports\CombustivelExport;
use App\Exports\MotoristasExport;
use App\Exports\FinanceiroExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class RelatorioExportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Exportar relatório de viagens
     */
    public function exportarViagens(Request $request)
    {
        try {
            Log::info('📥 Exportando viagens', $request->all());
            
            $request->validate([
                'dataInicio' => 'required|date',
                'dataFim' => 'required|date',
                'tipo' => 'required|in:todas,concluida,andamento,cancelada'
            ]);

            return Excel::download(
                new ViagensExport($request->dataInicio, $request->dataFim, $request->tipo),
                'viagens_' . date('Y-m-d_His') . '.xlsx'
            );
        } catch (\Exception $e) {
            Log::error('❌ Erro ao exportar viagens: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao exportar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar relatório da frota
     */
    public function exportarFrota(Request $request)
    {
        try {
            Log::info('📥 Exportando frota', $request->all());
            
            $request->validate([
                'dataInicio' => 'required|date',
                'dataFim' => 'required|date',
                'tipo' => 'required|in:todos,disponivel,manutencao'
            ]);

            return Excel::download(
                new FrotaExport($request->dataInicio, $request->dataFim, $request->tipo),
                'frota_' . date('Y-m-d_His') . '.xlsx'
            );
        } catch (\Exception $e) {
            Log::error('❌ Erro ao exportar frota: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao exportar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar relatório de motoristas
     */
    public function exportarMotoristas(Request $request)
    {
        try {
            Log::info('📥 Exportando motoristas', $request->all());
            
            $request->validate([
                'dataInicio' => 'required|date',
                'dataFim' => 'required|date',
                'tipo' => 'required|in:todos,ativos,inativos'
            ]);

            return Excel::download(
                new MotoristasExport($request->dataInicio, $request->dataFim, $request->tipo),
                'motoristas_' . date('Y-m-d_His') . '.xlsx'
            );
        } catch (\Exception $e) {
            Log::error('❌ Erro ao exportar motoristas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao exportar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar relatório de manutenção
     */
    public function exportarManutencao(Request $request)
    {
        try {
            Log::info('📥 Exportando manutenção', $request->all());
            
            $request->validate([
                'dataInicio' => 'required|date',
                'dataFim' => 'required|date',
                'tipo' => 'required|in:todos,preventiva,corretiva,inspecao'
            ]);

            return Excel::download(
                new ManutencaoExport($request->dataInicio, $request->dataFim, $request->tipo),
                'manutencao_' . date('Y-m-d_His') . '.xlsx'
            );
        } catch (\Exception $e) {
            Log::error('❌ Erro ao exportar manutenção: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao exportar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar relatório de combustível
     */
    public function exportarCombustivel(Request $request)
    {
        try {
            Log::info('📥 Exportando combustível', $request->all());
            
            $request->validate([
                'dataInicio' => 'required|date',
                'dataFim' => 'required|date',
                'tipo' => 'required|in:todos,interno,externo'
            ]);

            return Excel::download(
                new CombustivelExport($request->dataInicio, $request->dataFim, $request->tipo),
                'combustivel_' . date('Y-m-d_His') . '.xlsx'
            );
        } catch (\Exception $e) {
            Log::error('❌ Erro ao exportar combustível: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao exportar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar relatório financeiro
     */
    public function exportarFinanceiro(Request $request)
    {
        try {
            Log::info('📥 Exportando financeiro', $request->all());
            
            $request->validate([
                'dataInicio' => 'required|date',
                'dataFim' => 'required|date',
                'tipo' => 'required|in:geral,ordens,debito,credito,despesas'
            ]);

            $tipo = $request->tipo ?? 'geral';
            
            Log::info('📊 Tipo de exportação financeira: ' . $tipo);
            
            // Nomes de arquivo baseados no tipo
            $nomesArquivo = [
                'geral' => 'resumo_financeiro_geral',
                'ordens' => 'ordens_faturacao',
                'debito' => 'notas_debito',
                'credito' => 'notas_credito',
                'despesas' => 'despesas_motoristas'
            ];
            
            $nomeArquivo = ($nomesArquivo[$tipo] ?? 'financeiro') . '_' . date('Y-m-d_His') . '.xlsx';
            
            return Excel::download(
                new FinanceiroExport(
                    $request->dataInicio, 
                    $request->dataFim,
                    $tipo
                ),
                $nomeArquivo
            );
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao exportar financeiro: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao exportar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview de dados financeiros (opcional - para mostrar contagem antes de exportar)
     */
    public function previewFinanceiro(Request $request)
    {
        try {
            $request->validate([
                'dataInicio' => 'required|date',
                'dataFim' => 'required|date',
                'tipo' => 'required|in:geral,ordens,debito,credito,despesas'
            ]);

            $export = new FinanceiroExport(
                $request->dataInicio,
                $request->dataFim,
                $request->tipo
            );
            
            $dados = $export->array();
            $totalRegistros = count($dados);
            
            // Calcular totais
            $totalReceitas = 0;
            $totalDespesas = 0;
            
            foreach ($dados as $linha) {
                if (isset($linha['tipo']) && isset($linha['valor'])) {
                    if ($linha['tipo'] === 'RECEITA') {
                        $totalReceitas += floatval($linha['valor']);
                    } elseif ($linha['tipo'] === 'DESPESA') {
                        $totalDespesas += floatval($linha['valor']);
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_registros' => $totalRegistros,
                    'total_receitas' => round($totalReceitas, 2),
                    'total_despesas' => round($totalDespesas, 2),
                    'saldo' => round($totalReceitas - $totalDespesas, 2)
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao gerar preview financeiro: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao gerar preview: ' . $e->getMessage()
            ], 500);
        }
    }
}