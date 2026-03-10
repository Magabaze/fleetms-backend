<?php
// app/Http/Controllers/Api/Manutencao/ManutencaoPrintController.php

namespace App\Http\Controllers\Api\Manutencao;

use App\Http\Controllers\Controller;
use App\Models\Manutencao\OrdemTrabalho;
use App\Models\Manutencao\Avaria;
use App\Models\Manutencao\PlanoPreventivo;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class ManutencaoPrintController extends Controller
{
    /**
     * CONVERTER NÚMERO PARA EXTENSO
     */
    private function numeroPorExtenso($valor)
    {
        $valor = floatval($valor);
        if ($valor == 0) return 'zero meticais';
        
        $partes = explode('.', number_format($valor, 2, '.', ''));
        $reais = intval($partes[0]);
        $centavos = intval($partes[1] ?? 0);
        
        $unidades = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];
        $dezenas = ['', 'dez', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
        $centenas = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];
        $especiais = [
            11 => 'onze', 12 => 'doze', 13 => 'treze', 14 => 'quatorze',
            15 => 'quinze', 16 => 'dezesseis', 17 => 'dezessete',
            18 => 'dezoito', 19 => 'dezenove'
        ];
        
        $extenso = '';
        
        if ($reais >= 1000) {
            $milhares = floor($reais / 1000);
            $reais = $reais % 1000;
            
            if ($milhares == 1) {
                $extenso .= 'mil';
            } else {
                $extenso .= $this->numeroPorExtensoParcial($milhares, $unidades, $dezenas, $centenas, $especiais) . ' mil';
            }
            
            if ($reais > 0) $extenso .= ' e ';
        }
        
        if ($reais > 0) {
            $extenso .= $this->numeroPorExtensoParcial($reais, $unidades, $dezenas, $centenas, $especiais);
        }
        
        $extenso .= ' metical' . ($valor >= 2 ? 'is' : '');
        
        if ($centavos > 0) {
            $extenso .= ' e ' . $this->numeroPorExtensoParcial($centavos, $unidades, $dezenas, $centenas, $especiais);
            $extenso .= ' centavo' . ($centavos >= 2 ? 's' : '');
        }
        
        return $extenso;
    }

    /**
     * CONVERTER NÚMERO PARCIAL PARA EXTENSO
     */
    private function numeroPorExtensoParcial($num, $u, $d, $c, $e)
    {
        if ($num < 10) return $u[$num];
        if ($num >= 11 && $num <= 19) return $e[$num];
        if ($num < 100) {
            $dez = floor($num / 10);
            $uni = $num % 10;
            return $d[$dez] . ($uni > 0 ? ' e ' . $u[$uni] : '');
        }
        if ($num == 100) return 'cem';
        $cen = floor($num / 100);
        $resto = $num % 100;
        return $c[$cen] . ($resto > 0 ? ' e ' . $this->numeroPorExtensoParcial($resto, $u, $d, $c, $e) : '');
    }

    /**
     * CONVERTER DATA PARA EXTENSO
     */
    private function dataPorExtenso($data)
    {
        $dias = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
        $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        
        $t = strtotime($data);
        return "{$dias[date('w', $t)]}, " . date('d', $t) . " de {$meses[date('n', $t)-1]} de " . date('Y', $t);
    }

    /**
     * FORMATAR DATA (REMOVER PARTE TIME)
     */
    private function formatarData($data)
    {
        if (!$data) return '-';
        if (strpos($data, 'T') !== false) {
            return explode('T', $data)[0];
        }
        return date('Y-m-d', strtotime($data));
    }

    /**
     * IMPRIMIR ORDEM DE TRABALHO
     */
    public function printOrdemTrabalho(Request $request, $id)
    {
        try {
            Log::info('🖨️ Imprimindo ordem de trabalho', ['id' => $id]);

            // Autenticação via token na URL
            $token = $request->query('token');
            if ($token) {
                $user = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($user) Auth::login($user->tokenable);
            }

            if (!Auth::check()) {
                return response()->json(['error' => 'Não autenticado'], 401);
            }

            $user = Auth::user();

            $ordem = OrdemTrabalho::where('tenant_id', $user->tenant_id)->find($id);

            if (!$ordem) {
                return response()->json(['error' => 'Ordem de trabalho não encontrada'], 404);
            }

            $empresa = Empresa::where('tenant_id', $user->tenant_id)->first();

            // Buscar logo da empresa
            $logo_empresa = null;
            if ($empresa && $empresa->logo_url) {
                try {
                    if (\Storage::disk('r2')->exists($empresa->logo_url)) {
                        $imageData = \Storage::disk('r2')->get($empresa->logo_url);
                        $mimeType = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $imageData);
                        $logo_empresa = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                    }
                } catch (\Exception $e) {
                    Log::warning('Erro ao carregar logo: ' . $e->getMessage());
                }
            }

            // Mapeamento de tipos para labels
            $tipos = [
                'preventiva' => 'Preventiva',
                'corretiva' => 'Corretiva',
                'inspecao' => 'Inspeção',
            ];

            $status = [
                'pendente' => 'Pendente',
                'em_progresso' => 'Em Progresso',
                'concluida' => 'Concluída',
                'cancelada' => 'Cancelada',
            ];

            $prioridades = [
                'baixa' => 'Baixa',
                'media' => 'Média',
                'alta' => 'Alta',
                'urgente' => 'Urgente',
            ];

            // ✅ PREPARAR TODAS AS VARIÁVEIS
            $data = [
                'ordem' => $ordem,
                'empresa' => $empresa,
                'logo_empresa' => $logo_empresa,
                'titulo' => 'ORDEM DE TRABALHO',
                'tipo_label' => $tipos[$ordem->tipo] ?? $ordem->tipo,
                'status_label' => $status[$ordem->status] ?? $ordem->status,
                'prioridade_label' => $prioridades[$ordem->prioridade] ?? $ordem->prioridade,
                'cor' => '0aca7d',
                'cor_fundo' => 'f0fdf4',
                'data_criacao' => $this->formatarData($ordem->data_criacao),
                'data_prevista' => $this->formatarData($ordem->data_prevista),
                'data_extenso' => $this->dataPorExtenso($ordem->data_criacao),
                'data_emissao' => now()->format('d/m/Y H:i:s'),
                'current_date' => now()->format('d/m/Y H:i:s'),
                'usuario' => $user->name ?? 'Sistema',
            ];

            // Debug mode - retorna HTML
            if ($request->query('debug') === 'true') {
                $html = view('pdf.manutencao.ordem-trabalho', $data)->render();
                return response($html)->header('Content-Type', 'text/html');
            }

            // Gerar PDF com DOMPDF
            $pdf = Pdf::loadView('pdf.manutencao.ordem-trabalho', $data);
            $filename = 'OT_' . $ordem->codigo . '_' . date('Ymd_His') . '.pdf';

            if ($request->query('download') === '1') {
                return $pdf->download($filename);
            }

            return $pdf->stream($filename);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao imprimir ordem: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * IMPRIMIR AVARIA
     */
    public function printAvaria(Request $request, $id)
    {
        try {
            Log::info('🖨️ Imprimindo avaria', ['id' => $id]);

            // Autenticação via token na URL
            $token = $request->query('token');
            if ($token) {
                $user = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($user) Auth::login($user->tokenable);
            }

            if (!Auth::check()) {
                return response()->json(['error' => 'Não autenticado'], 401);
            }

            $user = Auth::user();

            $avaria = Avaria::where('tenant_id', $user->tenant_id)->find($id);

            if (!$avaria) {
                return response()->json(['error' => 'Avaria não encontrada'], 404);
            }

            $empresa = Empresa::where('tenant_id', $user->tenant_id)->first();

            // Buscar logo da empresa
            $logo_empresa = null;
            if ($empresa && $empresa->logo_url) {
                try {
                    if (\Storage::disk('r2')->exists($empresa->logo_url)) {
                        $imageData = \Storage::disk('r2')->get($empresa->logo_url);
                        $mimeType = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $imageData);
                        $logo_empresa = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                    }
                } catch (\Exception $e) {
                    Log::warning('Erro ao carregar logo: ' . $e->getMessage());
                }
            }

            // Mapeamento de status
            $status = [
                'aberta' => 'Aberta',
                'em_diagnostico' => 'Em Diagnóstico',
                'em_reparacao' => 'Em Reparação',
                'resolvida' => 'Resolvida',
            ];

            $prioridades = [
                'normal' => 'Normal',
                'alta' => 'Alta',
                'urgente' => 'Urgente',
            ];

            // ✅ PREPARAR TODAS AS VARIÁVEIS
            $data = [
                'avaria' => $avaria,
                'empresa' => $empresa,
                'logo_empresa' => $logo_empresa,
                'titulo' => 'REGISTO DE AVARIA',
                'status_label' => $status[$avaria->status] ?? $avaria->status,
                'prioridade_label' => $prioridades[$avaria->prioridade] ?? $avaria->prioridade,
                'cor' => 'dc2626',
                'cor_fundo' => 'fee2e2',
                'data_reporte' => $this->formatarData($avaria->data_reporte),
                'data_extenso' => $this->dataPorExtenso($avaria->data_reporte),
                'data_emissao' => now()->format('d/m/Y H:i:s'),
                'current_date' => now()->format('d/m/Y H:i:s'),
                'usuario' => $user->name ?? 'Sistema',
            ];

            // Debug mode
            if ($request->query('debug') === 'true') {
                $html = view('pdf.manutencao.avaria', $data)->render();
                return response($html)->header('Content-Type', 'text/html');
            }

            // Gerar PDF
            $pdf = Pdf::loadView('pdf.manutencao.avaria', $data);
            $filename = 'AVARIA_' . $avaria->codigo . '_' . date('Ymd_His') . '.pdf';

            if ($request->query('download') === '1') {
                return $pdf->download($filename);
            }

            return $pdf->stream($filename);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao imprimir avaria: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * IMPRIMIR PLANO PREVENTIVO
     */
    public function printPlanoPreventivo(Request $request, $id)
    {
        try {
            Log::info('🖨️ Imprimindo plano preventivo', ['id' => $id]);

            // Autenticação via token na URL
            $token = $request->query('token');
            if ($token) {
                $user = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($user) Auth::login($user->tokenable);
            }

            if (!Auth::check()) {
                return response()->json(['error' => 'Não autenticado'], 401);
            }

            $user = Auth::user();

            $plano = PlanoPreventivo::where('tenant_id', $user->tenant_id)->find($id);

            if (!$plano) {
                return response()->json(['error' => 'Plano preventivo não encontrado'], 404);
            }

            $empresa = Empresa::where('tenant_id', $user->tenant_id)->first();

            // Buscar logo da empresa
            $logo_empresa = null;
            if ($empresa && $empresa->logo_url) {
                try {
                    if (\Storage::disk('r2')->exists($empresa->logo_url)) {
                        $imageData = \Storage::disk('r2')->get($empresa->logo_url);
                        $mimeType = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $imageData);
                        $logo_empresa = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                    }
                } catch (\Exception $e) {
                    Log::warning('Erro ao carregar logo: ' . $e->getMessage());
                }
            }

            // Mapeamento de status
            $status = [
                'ok' => 'Em Dia',
                'alerta' => 'Alerta',
                'vencido' => 'Vencido',
            ];

            // Calcular progresso e KM restantes
            $kmRestantes = ($plano->ultimo_km + $plano->intervalo_km) - $plano->km_atual;
            $progresso = min((($plano->km_atual - $plano->ultimo_km) / $plano->intervalo_km) * 100, 100);

            // ✅ PREPARAR TODAS AS VARIÁVEIS
            $data = [
                'plano' => $plano,
                'empresa' => $empresa,
                'logo_empresa' => $logo_empresa,
                'titulo' => 'PLANO DE MANUTENÇÃO PREVENTIVA',
                'status_label' => $status[$plano->status] ?? $plano->status,
                'cor' => '7c3aed',
                'cor_fundo' => 'f5f3ff',
                'km_restantes' => $kmRestantes,
                'progresso' => round($progresso, 1),
                'ultima_data' => $this->formatarData($plano->ultima_data),
                'proxima_data' => $this->formatarData($plano->proxima_data),
                'data_extenso' => $this->dataPorExtenso($plano->ultima_data),
                'data_emissao' => now()->format('d/m/Y H:i:s'),
                'current_date' => now()->format('d/m/Y H:i:s'),
                'usuario' => $user->name ?? 'Sistema',
            ];

            // Debug mode
            if ($request->query('debug') === 'true') {
                $html = view('pdf.manutencao.plano-preventivo', $data)->render();
                return response($html)->header('Content-Type', 'text/html');
            }

            // Gerar PDF
            $pdf = Pdf::loadView('pdf.manutencao.plano-preventivo', $data);
            $filename = 'PLANO_' . $plano->id . '_' . date('Ymd_His') . '.pdf';

            if ($request->query('download') === '1') {
                return $pdf->download($filename);
            }

            return $pdf->stream($filename);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao imprimir plano: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}