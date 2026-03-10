<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotaFiscal;
use App\Models\OrdemFaturacao;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class FaturacaoPrintController extends Controller
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
        $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
                  'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        $t = strtotime($data);
        return date('d', $t) . " de {$meses[date('n', $t)-1]} de " . date('Y', $t);
    }

    /**
     * IMPRIMIR NOTA FISCAL (CRÉDITO OU DÉBITO)
     */
    public function printNota(Request $request, $id)
    {
        try {
            Log::info('🖨️ Imprimindo nota fiscal', ['id' => $id, 'tipo' => $request->query('tipo')]);

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
            $tipo = $request->query('tipo', 'credito');

            $nota = NotaFiscal::with('ordem')->where('tipo', $tipo)->find($id);

            if (!$nota) {
                return response()->json(['error' => 'Nota não encontrada'], 404);
            }

            if ($nota->tenant_id != $user->tenant_id) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $empresa = Empresa::where('tenant_id', $user->tenant_id)->first();

            // Ambos os tipos usam as mesmas cores neutras — o badge já identifica o tipo
            $tipo_info = [
                'titulo'    => $tipo == 'credito' ? 'Nota de Crédito' : 'Nota de Débito',
                'cor'       => '#013334',
                'cor_fundo' => '#e8f0f0',
                'classe'    => $tipo == 'credito' ? 'nota-credito' : 'nota-debito',
                'sinal'     => '', // removido — não é profissional mostrar +/-
            ];

            $data = [
                'nota'             => $nota,
                'empresa'          => $empresa,
                'tipo_info'        => $tipo_info,
                'numero_formatado' => $nota->numero,
                'logo_empresa'     => $empresa->logo ?? null,
                'valor_formatado'  => number_format($nota->valor, 2, ',', '.'),
                'valor_extenso'    => $this->numeroPorExtenso($nota->valor),
                'data_emissao'     => date('d/m/Y', strtotime($nota->data)),
                'data_extenso'     => $this->dataPorExtenso($nota->data),
                'criadoPor'        => $user->name ?? 'Sistema',
                'current_date'     => now()->format('d/m/Y H:i:s'),
            ];

            if ($request->query('debug') === 'true') {
                return response(view('pdf.nota-fiscal', $data)->render())
                    ->header('Content-Type', 'text/html');
            }

            $pdf = Pdf::loadView('pdf.nota-fiscal', $data);
            $filename = ($tipo == 'credito' ? 'NC' : 'ND') . '_' . $nota->numero . '_' . date('Ymd_His') . '.pdf';

            return $request->query('download') === '1'
                ? $pdf->download($filename)
                : $pdf->stream($filename);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao imprimir nota: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * IMPRIMIR ORDEM DE FATURAÇÃO
     */
    public function printOrdem(Request $request, $id)
    {
        try {
            Log::info('🖨️ Imprimindo ordem', ['id' => $id]);

            $token = $request->query('token');
            if ($token) {
                $user = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($user) Auth::login($user->tokenable);
            }

            if (!Auth::check()) {
                return response()->json(['error' => 'Não autenticado'], 401);
            }

            $user = Auth::user();

            $ordem = OrdemFaturacao::with(['viagem', 'notasFiscais'])->find($id);

            if (!$ordem) {
                return response()->json(['error' => 'Ordem não encontrada'], 404);
            }

            if ($ordem->tenant_id != $user->tenant_id) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $empresa = Empresa::where('tenant_id', $user->tenant_id)->first();

            $totalCredito = $ordem->notasFiscais->where('tipo', 'credito')->sum('valor');
            $totalDebito  = $ordem->notasFiscais->where('tipo', 'debito')->sum('valor');
            $saldo        = $ordem->valor + $totalDebito - $totalCredito;

            $statusMap = [
                'pendente'    => 'PENDENTE',
                'processado'  => 'FATURADO',
                'cancelado'   => 'CANCELADO',
            ];

            $data = [
                'ordem'               => $ordem,
                'viagem'              => $ordem->viagem,
                'notas'               => $ordem->notasFiscais,
                'empresa'             => $empresa,
                'logo_empresa'        => $empresa->logo ?? null,
                'status'              => $statusMap[$ordem->status] ?? strtoupper($ordem->status),
                'servicos_formatado'  => number_format($ordem->valor, 2, ',', '.'),
                'creditos_formatado'  => number_format($totalCredito, 2, ',', '.'),
                'debitos_formatado'   => number_format($totalDebito, 2, ',', '.'),
                'saldo_formatado'     => number_format($saldo, 2, ',', '.'),
                'valor_extenso'       => $this->numeroPorExtenso($saldo),
                'totais' => [
                    'servicos' => $ordem->valor,
                    'creditos' => $totalCredito,
                    'debitos'  => $totalDebito,
                    'saldo'    => $saldo,
                ],
                'data_emissao'        => date('d/m/Y'),
                'data_extenso'        => $this->dataPorExtenso(date('Y-m-d')),
                'criadoPor'           => $user->name ?? 'Sistema',
                'current_date'        => now()->format('d/m/Y H:i:s'),
            ];

            if ($request->query('debug') === 'true') {
                return response(view('pdf.faturacao-ordem', $data)->render())
                    ->header('Content-Type', 'text/html');
            }

            $pdf = Pdf::loadView('pdf.faturacao-ordem', $data);
            $filename = 'ORDEM_' . $ordem->codigo . '_' . date('Ymd_His') . '.pdf';

            return $request->query('download') === '1'
                ? $pdf->download($filename)
                : $pdf->stream($filename);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao imprimir ordem: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}