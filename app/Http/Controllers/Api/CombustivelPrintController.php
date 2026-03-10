<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Combustivel\PedidoCompra;
use App\Models\Combustivel\AbastecimentoExterno;
use App\Models\Combustivel\AbastecimentoInterno;
use App\Models\Empresa;
use App\Models\Viagem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class CombustivelPrintController extends Controller
{
    /**
     * CONVERTER NÚMERO PARA EXTENSO (Suporta Milhões)
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

        // ===== LÓGICA PARA MILHÕES =====
        if ($reais >= 1000000) {
            $milhoes = floor($reais / 1000000);
            $reais = $reais % 1000000;

            $extensoMilhoes = $this->numeroPorExtensoParcial($milhoes, $unidades, $dezenas, $centenas, $especiais);
            
            if ($milhoes == 1) {
                $extenso .= 'um milhão';
            } else {
                $extenso .= $extensoMilhoes . ' milhões';
            }

            if ($reais > 0) $extenso .= ', ';
        }

        // ===== LÓGICA PARA MILHARES =====
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

        // ===== LÓGICA PARA CENTENAS/UNIDADES RESTANTES =====
        if ($reais > 0) {
            $extenso .= $this->numeroPorExtensoParcial($reais, $unidades, $dezenas, $centenas, $especiais);
        }

        // Moeda
        if ($valor >= 2) {
            $extenso .= ' meticais';
        } else {
            $extenso .= ' metical';
        }

        // ===== CENTAVOS =====
        if ($centavos > 0) {
            $extenso .= ' e ' . $this->numeroPorExtensoParcial($centavos, $unidades, $dezenas, $centenas, $especiais);
            $extenso .= ' centavo' . ($centavos >= 2 ? 's' : '');
        }

        return $extenso;
    }

    /**
     * CONVERTER NÚMERO PARCIAL PARA EXTENSO (0 a 999)
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

    /**
     * IMPRIMIR ABASTECIMENTO EXTERNO
     */
    public function printAbastecimentoExterno(Request $request, $id)
    {
        try {
            Log::info('🖨️ Imprimindo abastecimento externo', ['id' => $id]);

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

            $abastecimento = AbastecimentoExterno::with(['veiculo', 'motorista', 'posto'])
                ->where('tenant_id', $user->tenant_id)
                ->find($id);

            if (!$abastecimento) {
                return response()->json(['error' => 'Abastecimento não encontrado'], 404);
            }

            $empresa = Empresa::where('tenant_id', $user->tenant_id)->first();

            // Buscar viagem se existir
            $viagem = null;
            if ($abastecimento->viagem_id) {
                $viagem = Viagem::find($abastecimento->viagem_id);
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

            // PREPARAR TODAS AS VARIÁVEIS
            $data = [
                'abastecimento' => $abastecimento,
                'viagem' => $viagem,
                'empresa' => $empresa,
                'logo_empresa' => $logo_empresa,
                'tipo' => $request->query('tipo', 'original'),
                'copia' => $request->query('copia', 'false'),
                
                'titulo' => 'COMPROVANTE DE ABASTECIMENTO',
                'subtitulo' => 'Abastecimento Externo',
                'cor' => '059669',
                'cor_fundo' => 'f0fdf4',
                
                'valor_formatado' => number_format($abastecimento->valor_total, 2, ',', '.'),
                'valor_extenso' => $this->numeroPorExtenso($abastecimento->valor_total),
                'quantidade_formatada' => number_format($abastecimento->quantidade, 2, ',', '.'),
                'preco_formatado' => number_format($abastecimento->preco_unitario, 2, ',', '.'),
                
                'data_abastecimento' => date('d/m/Y', strtotime($abastecimento->data_abastecimento)),
                'data_extenso' => $this->dataPorExtenso($abastecimento->data_abastecimento),
                'data_emissao' => now()->format('d/m/Y H:i:s'),
                'current_date' => now()->format('d/m/Y H:i:s'),
                
                'usuario' => $user->name ?? 'Sistema',
            ];

            // Debug mode - retorna HTML
            if ($request->query('debug') === 'true') {
                $html = view('pdf.abastecimento-externo', $data)->render();
                return response($html)->header('Content-Type', 'text/html');
            }

            // Gerar PDF com DOMPDF
            $pdf = Pdf::loadView('pdf.abastecimento-externo', $data);
            $filename = 'ABASTECIMENTO_' . ($abastecimento->numero ?? $abastecimento->id) . '_' . date('Ymd_His') . '.pdf';

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
     * IMPRIMIR ABASTECIMENTO INTERNO
     */
    public function printAbastecimentoInterno(Request $request, $id)
    {
        try {
            Log::info('🖨️ Imprimindo abastecimento interno', ['id' => $id]);

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

            $abastecimento = AbastecimentoInterno::with(['camiao', 'motorista', 'viagem', 'tanque'])
                ->where('tenant_id', $user->tenant_id)
                ->find($id);

            if (!$abastecimento) {
                return response()->json(['error' => 'Abastecimento não encontrado'], 404);
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

            // PREPARAR TODAS AS VARIÁVEIS
            $data = [
                'abastecimento' => $abastecimento,
                'empresa' => $empresa,
                'logo_empresa' => $logo_empresa,
                'tipo' => $request->query('tipo', 'original'),
                'copia' => $request->query('copia', 'false'),
                
                'titulo' => 'COMPROVANTE DE ABASTECIMENTO',
                'subtitulo' => 'Abastecimento Interno',
                'cor' => '7c3aed',
                'cor_fundo' => 'f5f3ff',
                
                'quantidade_formatada' => number_format($abastecimento->quantidade, 2, ',', '.'),
                'data_abastecimento' => date('d/m/Y', strtotime($abastecimento->data_abastecimento)),
                'hora_abastecimento' => $abastecimento->hora_abastecimento,
                'data_extenso' => $this->dataPorExtenso($abastecimento->data_abastecimento),
                'data_emissao' => now()->format('d/m/Y H:i:s'),
                'current_date' => now()->format('d/m/Y H:i:s'),
                'usuario' => $user->name ?? 'Sistema',
            ];

            // Debug mode
            if ($request->query('debug') === 'true') {
                $html = view('pdf.abastecimento-interno', $data)->render();
                return response($html)->header('Content-Type', 'text/html');
            }

            // Gerar PDF com DOMPDF
            $pdf = Pdf::loadView('pdf.abastecimento-interno', $data);
            $filename = 'ABASTECIMENTO_INTERNO_' . ($abastecimento->numero ?? $abastecimento->id) . '_' . date('Ymd_His') . '.pdf';

            if ($request->query('download') === '1') {
                return $pdf->download($filename);
            }

            return $pdf->stream($filename);

        } catch (\Exception $e) {
            Log::error('❌ Erro: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * IMPRIMIR PEDIDO DE COMPRA
     */
    public function printPedidoCompra(Request $request, $id)
    {
        try {
            Log::info('🖨️ Imprimindo pedido de compra', ['id' => $id]);

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

            $pedido = PedidoCompra::where('tenant_id', $user->tenant_id)->find($id);

            if (!$pedido) {
                return response()->json(['error' => 'Pedido não encontrado'], 404);
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

            // PREPARAR TODAS AS VARIÁVEIS
            $data = [
                'pedido' => $pedido,
                'empresa' => $empresa,
                'logo_empresa' => $logo_empresa,
                'tipo' => $request->query('tipo', 'original'), // ✅ VARIÁVEL ADICIONADA
                'copia' => $request->query('copia', 'false'),
                
                'titulo' => 'PEDIDO DE COMPRA',
                'cor' => '2563eb',
                'cor_fundo' => 'eff6ff',
                
                'valor_formatado' => number_format($pedido->valor_total, 2, ',', '.'),
                'valor_extenso' => $this->numeroPorExtenso($pedido->valor_total),
                'quantidade_formatada' => number_format($pedido->quantidade, 2, ',', '.'),
                'preco_formatado' => number_format($pedido->preco_unitario, 2, ',', '.'),
                
                'data_pedido' => $pedido->data_pedido ? date('d/m/Y', strtotime($pedido->data_pedido)) : '--/--/----',
                'data_entrega_prevista' => $pedido->data_entrega_prevista ? date('d/m/Y', strtotime($pedido->data_entrega_prevista)) : '--/--/----',
                'data_entrega_real' => $pedido->data_entrega_real ? date('d/m/Y', strtotime($pedido->data_entrega_real)) : null,
                'data_extenso' => $pedido->data_pedido ? $this->dataPorExtenso($pedido->data_pedido) : '',
                'data_emissao' => now()->format('d/m/Y H:i:s'),
                'current_date' => now()->format('d/m/Y H:i:s'),
                'usuario' => $user->name ?? 'Sistema',
            ];

            // Debug mode
            if ($request->query('debug') === 'true') {
                $html = view('pdf.pedido-compra', $data)->render();
                return response($html)->header('Content-Type', 'text/html');
            }

            // Gerar PDF com DOMPDF
            $pdf = Pdf::loadView('pdf.pedido-compra', $data);
            $filename = 'PEDIDO_' . $pedido->numero . '_' . date('Ymd_His') . '.pdf';

            if ($request->query('download') === '1') {
                return $pdf->download($filename);
            }

            return $pdf->stream($filename);

        } catch (\Exception $e) {
            Log::error('❌ Erro: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}