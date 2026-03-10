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
                'tipo' => 'required|in:todas,concluida,andamento,cancelada,pendente'
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
     * Exportar relatório financeiro
     */
    public function exportarFinanceiro(Request $request)
    {
        try {
            Log::info('📥 Exportando financeiro', $request->all());
            
            $request->validate([
                'dataInicio' => 'required|date',
                'dataFim' => 'required|date'
            ]);

            return Excel::download(
                new FinanceiroExport($request->dataInicio, $request->dataFim),
                'financeiro_' . date('Y-m-d_His') . '.xlsx'
            );
        } catch (\Exception $e) {
            Log::error('❌ Erro ao exportar financeiro: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao exportar: ' . $e->getMessage()
            ], 500);
        }
    }
}