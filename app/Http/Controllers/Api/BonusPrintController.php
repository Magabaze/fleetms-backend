<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarteiraPagamento;
use App\Models\CarteiraMovimento;
use App\Models\Bonus;
use App\Models\Desconto;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class BonusPrintController extends Controller
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
     * IMPRIMIR COMPROVANTE DE PAGAMENTO
     */
    public function printPagamento(Request $request, $id)
    {
        try {
            Log::info('🖨️ Imprimindo comprovante de pagamento', ['id' => $id]);

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

            // Buscar pagamento
            $pagamento = CarteiraPagamento::where('tenant_id', $user->tenant_id)
                ->where('id', $id)
                ->first();

            if (!$pagamento) {
                return response()->json(['error' => 'Pagamento não encontrado'], 404);
            }

            // Buscar todos os bônus do motorista (aprovados)
            $bonus = Bonus::where('motorista', $pagamento->motorista)
                ->where('tenant_id', $user->tenant_id)
                ->where('status', 'approved')
                ->orderBy('created_at', 'desc')
                ->get();

            // Buscar todos os descontos do motorista (aplicados)
            $descontos = Desconto::where('motorista', $pagamento->motorista)
                ->where('tenant_id', $user->tenant_id)
                ->where('status', 'aplicado')
                ->orderBy('data_desconto', 'desc')
                ->get();

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

            // Preparar dados para a view
            $data = [
                'pagamento' => $pagamento,
                'bonus' => $bonus,
                'descontos' => $descontos,
                'empresa' => $empresa,
                'logo_empresa' => $logo_empresa,
                'titulo' => 'COMPROVANTE DE PAGAMENTO',
                'subtitulo' => 'Bónus e Descontos',
                'cor' => '0aca7d',
                'cor_fundo' => 'f0fdf4',
                'valor_formatado' => number_format($pagamento->valor, 2, ',', '.'),
                'desconto_formatado' => number_format($pagamento->desconto_aplicado, 2, ',', '.'),
                'total_bonus' => number_format($bonus->sum('valor'), 2, ',', '.'),
                'total_descontos' => number_format($descontos->sum('valor'), 2, ',', '.'),
                'valor_extenso' => $this->numeroPorExtenso($pagamento->valor),
                'data_pagamento' => date('d/m/Y', strtotime($pagamento->created_at)),
                'data_extenso' => $this->dataPorExtenso($pagamento->created_at),
                'data_emissao' => now()->format('d/m/Y H:i:s'),
                'current_date' => now()->format('d/m/Y H:i:s'),
                'usuario' => $user->name ?? 'Sistema',
            ];

            // Debug mode - retorna HTML
            if ($request->query('debug') === 'true') {
                $html = view('pdf.comprovante-pagamento', $data)->render();
                return response($html)->header('Content-Type', 'text/html');
            }

            // Gerar PDF com DOMPDF
            $pdf = Pdf::loadView('pdf.comprovante-pagamento', $data);
            $filename = 'PAGAMENTO_' . $pagamento->motorista . '_' . date('Ymd_His') . '.pdf';

            if ($request->query('download') === '1') {
                return $pdf->download($filename);
            }

            return $pdf->stream($filename);

        } catch (\Exception $e) {
            Log::error('❌ Erro: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * IMPRIMIR EXTRATO DO MOTORISTA POR PERÍODO
     */
    public function printExtrato(Request $request)
    {
        try {
            Log::info('🖨️ Imprimindo extrato do motorista', $request->all());

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

            $motorista = $request->query('motorista');
            $dataInicio = $request->query('data_inicio');
            $dataFim = $request->query('data_fim');

            if (!$motorista || !$dataInicio || !$dataFim) {
                return response()->json(['error' => 'Parâmetros incompletos'], 400);
            }

            // Buscar movimentos do período
            $movimentos = CarteiraMovimento::where('motorista', $motorista)
                ->where('tenant_id', $user->tenant_id)
                ->whereBetween('created_at', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
                ->orderBy('created_at', 'asc')
                ->get();

            // Buscar pagamentos do período
            $pagamentos = CarteiraPagamento::where('motorista', $motorista)
                ->where('tenant_id', $user->tenant_id)
                ->whereBetween('created_at', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
                ->orderBy('created_at', 'asc')
                ->get();

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

            // Calcular totais
            $totalCreditos = $movimentos->where('tipo', 'credito')->sum('valor');
            $totalDebitos = $movimentos->where('tipo', 'debito')->sum('valor');
            $totalPagamentos = $pagamentos->sum('valor');

            $saldoInicial = 0;
            $saldoFinal = $saldoInicial + $totalCreditos - $totalDebitos - $totalPagamentos;

            // Preparar dados para a view
            $data = [
                'motorista' => $motorista,
                'movimentos' => $movimentos,
                'pagamentos' => $pagamentos,
                'empresa' => $empresa,
                'logo_empresa' => $logo_empresa,
                'titulo' => 'EXTRATO DA CARTEIRA',
                'subtitulo' => "Período: " . date('d/m/Y', strtotime($dataInicio)) . " a " . date('d/m/Y', strtotime($dataFim)),
                'cor' => '0aca7d',
                'cor_fundo' => 'f0fdf4',
                'data_inicio' => date('d/m/Y', strtotime($dataInicio)),
                'data_fim' => date('d/m/Y', strtotime($dataFim)),
                'total_creditos' => number_format($totalCreditos, 2, ',', '.'),
                'total_debitos' => number_format($totalDebitos, 2, ',', '.'),
                'total_pagamentos' => number_format($totalPagamentos, 2, ',', '.'),
                'saldo_inicial' => number_format($saldoInicial, 2, ',', '.'),
                'saldo_final' => number_format($saldoFinal, 2, ',', '.'),
                'data_emissao' => now()->format('d/m/Y H:i:s'),
                'current_date' => now()->format('d/m/Y H:i:s'),
                'usuario' => $user->name ?? 'Sistema',
            ];

            // Debug mode - retorna HTML
            if ($request->query('debug') === 'true') {
                $html = view('pdf.extrato-carteira', $data)->render();
                return response($html)->header('Content-Type', 'text/html');
            }

            // Gerar PDF com DOMPDF
            $pdf = Pdf::loadView('pdf.extrato-carteira', $data);
            $filename = 'EXTRATO_' . $motorista . '_' . date('Ymd_His') . '.pdf';

            if ($request->query('download') === '1') {
                return $pdf->download($filename);
            }

            return $pdf->stream($filename);

        } catch (\Exception $e) {
            Log::error('❌ Erro: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}