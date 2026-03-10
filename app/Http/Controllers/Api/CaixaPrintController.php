<?php
// app/Http/Controllers/Api/CaixaPrintController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Viagem;
use App\Models\DriverExpense; // 👈 CORREÇÃO: Usando o modelo correto
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class CaixaPrintController extends Controller
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

        if ($valor >= 2) {
            $extenso .= ' meticais';
        } else {
            $extenso .= ' metical';
        }

        if ($centavos > 0) {
            $extenso .= ' e ' . $this->numeroPorExtensoParcial($centavos, $unidades, $dezenas, $centenas, $especiais);
            $extenso .= ' centavo' . ($centavos >= 2 ? 's' : '');
        }

        return $extenso;
    }

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

    private function dataPorExtenso($data)
    {
        if (!$data) return 'Data não informada';
        
        $dias = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
        $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        
        try {
            $t = strtotime($data);
            if (!$t) return 'Data inválida';
            
            return "{$dias[date('w', $t)]}, " . date('d', $t) . " de {$meses[date('n', $t)-1]} de " . date('Y', $t);
        } catch (\Exception $e) {
            return 'Data inválida';
        }
    }

    public function printJustificativo(Request $request, $viagemId)
    {
        try {
            Log::info('🖨️ Imprimindo justificativo de viagem', ['viagem_id' => $viagemId]);

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

            // Buscar viagem
            $viagem = Viagem::where('tenant_id', $user->tenant_id)
                ->find($viagemId);

            if (!$viagem) {
                return response()->json(['error' => 'Viagem não encontrada'], 404);
            }

            // ✅ CORREÇÃO: Usando DriverExpense em vez de Despesa
            $despesas = DriverExpense::where('viagem_id', $viagemId)
                ->where('is_active', true) // Adicionado filtro de ativas
                ->orderBy('created_at')
                ->get();

            $empresa = Empresa::where('tenant_id', $user->tenant_id)->first();

            // Agrupar despesas por status
            $despesasPendentes = $despesas->where('status', 'paid');
            $despesasJustificadas = $despesas->where('status', 'settled');

            // Calcular totais por moeda
            $totais = [];
            foreach ($despesas as $despesa) {
                $moeda = $despesa->currency;
                if (!isset($totais[$moeda])) {
                    $totais[$moeda] = 0;
                }
                $totais[$moeda] += $despesa->amount;
            }

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

            // Preparar dados da view
            $data = [
                'viagem' => $viagem,
                'despesas' => $despesas,
                'despesasPendentes' => $despesasPendentes,
                'despesasJustificadas' => $despesasJustificadas,
                'empresa' => $empresa,
                'logo_empresa' => $logo_empresa,
                'tipo' => $request->query('tipo', 'original'),
                'copia' => $request->query('copia', 'false'),
                'totais' => $totais,
                
                'titulo' => 'JUSTIFICATIVO DE DESPESAS',
                'subtitulo' => 'Viagem ' . $viagem->trip_number,
                'cor' => '0aca7d',
                'cor_fundo' => 'f0fdf4',
                
                'data_viagem' => $viagem->schedule_date ? date('d/m/Y', strtotime($viagem->schedule_date)) : 'N/I',
                'data_extenso' => $this->dataPorExtenso($viagem->schedule_date),
                'data_emissao' => now()->format('d/m/Y H:i:s'),
                'current_date' => now()->format('d/m/Y H:i:s'),
                
                'usuario' => $user->name ?? 'Sistema',
                
                'km_previsto' => number_format($viagem->km_previsto ?? 0, 0, ',', '.'),
                'km_real' => number_format($viagem->km_real ?? 0, 0, ',', '.'),
            ];

            // Formatar totais
            $totaisFormatados = [];
            $valorExtenso = '';
            foreach ($totais as $moeda => $valor) {
                $totaisFormatados[$moeda] = number_format($valor, 2, ',', '.');
                if (empty($valorExtenso)) {
                    $valorExtenso = $this->numeroPorExtenso($valor);
                }
            }
            $data['totaisFormatados'] = $totaisFormatados;
            $data['valorExtenso'] = $valorExtenso;

            // Debug mode
            if ($request->query('debug') === 'true') {
                $html = view('pdf.justificativo-viagem', $data)->render();
                return response($html)->header('Content-Type', 'text/html');
            }

            // Gerar PDF
            $pdf = Pdf::loadView('pdf.justificativo-viagem', $data);
            $pdf->setPaper('A4', 'portrait');
            
            $filename = 'JUSTIFICATIVO_' . $viagem->trip_number . '_' . date('Ymd_His') . '.pdf';

            if ($request->query('download') === '1') {
                return $pdf->download($filename);
            }

            return $pdf->stream($filename);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao gerar justificativo: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function printResumoDespesas(Request $request, $viagemId)
    {
        try {
            Log::info('🖨️ Imprimindo resumo de despesas', ['viagem_id' => $viagemId]);

            $token = $request->query('token');
            if ($token) {
                $user = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($user) Auth::login($user->tokenable);
            }

            if (!Auth::check()) {
                return response()->json(['error' => 'Não autenticado'], 401);
            }

            $user = Auth::user();

            $viagem = Viagem::where('tenant_id', $user->tenant_id)
                ->find($viagemId);

            if (!$viagem) {
                return response()->json(['error' => 'Viagem não encontrada'], 404);
            }

            // ✅ CORREÇÃO: Usando DriverExpense
            $despesas = DriverExpense::where('viagem_id', $viagemId)
                ->where('is_active', true)
                ->orderBy('created_at')
                ->get();

            $empresa = Empresa::where('tenant_id', $user->tenant_id)->first();
            $despesasPorTipo = $despesas->groupBy('expense_head');

            $data = [
                'viagem' => $viagem,
                'despesas' => $despesas,
                'despesasPorTipo' => $despesasPorTipo,
                'empresa' => $empresa,
                'data_emissao' => now()->format('d/m/Y H:i:s'),
                'usuario' => $user->name ?? 'Sistema',
            ];

            if ($request->query('debug') === 'true') {
                $html = view('pdf.resumo-despesas', $data)->render();
                return response($html)->header('Content-Type', 'text/html');
            }

            $pdf = Pdf::loadView('pdf.resumo-despesas', $data);
            $filename = 'RESUMO_DESPESAS_' . $viagem->trip_number . '_' . date('Ymd_His') . '.pdf';

            if ($request->query('download') === '1') {
                return $pdf->download($filename);
            }

            return $pdf->stream($filename);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao gerar resumo: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}