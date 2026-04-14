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
use Illuminate\Support\Facades\Storage;
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

        // MILHÕES
        if ($reais >= 1000000) {
            $milhoes = floor($reais / 1000000);
            $reais = $reais % 1000000;
            $extensoMilhoes = $this->numeroPorExtensoParcial($milhoes, $unidades, $dezenas, $centenas, $especiais);
            $extenso .= $milhoes == 1 ? 'um milhão' : $extensoMilhoes . ' milhões';
            if ($reais > 0) $extenso .= ', ';
        }

        // MILHARES
        if ($reais >= 1000) {
            $milhares = floor($reais / 1000);
            $reais = $reais % 1000;
            $extenso .= $milhares == 1 ? 'mil' : $this->numeroPorExtensoParcial($milhares, $unidades, $dezenas, $centenas, $especiais) . ' mil';
            if ($reais > 0) $extenso .= ' e ';
        }

        // CENTENAS/UNIDADES
        if ($reais > 0) {
            $extenso .= $this->numeroPorExtensoParcial($reais, $unidades, $dezenas, $centenas, $especiais);
        }

        $extenso .= $valor >= 2 ? ' meticais' : ' metical';

        // CENTAVOS
        if ($centavos > 0) {
            $extenso .= ' e ' . $this->numeroPorExtensoParcial($centavos, $unidades, $dezenas, $centenas, $especiais);
            $extenso .= $centavos >= 2 ? ' centavos' : ' centavo';
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

    /**
     * CONVERTER DATA PARA EXTENSO
     */
    private function dataPorExtenso($data)
    {
        if (!$data) return 'Data não informada';
        $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        try {
            $t = strtotime($data);
            if (!$t) return 'Data inválida';
            return date('d', $t) . ' de ' . $meses[date('n', $t) - 1] . ' de ' . date('Y', $t);
        } catch (\Exception $e) {
            return 'Data inválida';
        }
    }

    /**
     * CONVERTE IMAGEM PARA BASE64 DE FORMA ROBUSTA
     */
    private function getImageBase64($path)
    {
        if (empty($path)) {
            Log::warning('getImageBase64: caminho vazio');
            return null;
        }

        try {
            // Se já for base64, retorna diretamente
            if (strpos($path, 'data:image') === 0) {
                return $path;
            }

            Log::info('🔍 Tentando obter imagem', ['path' => $path]);

            // 1. Tenta via Storage disk R2
            if (Storage::disk('r2')->exists($path)) {
                $imageData = Storage::disk('r2')->get($path);
                if ($imageData) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_buffer($finfo, $imageData);
                    finfo_close($finfo);

                    if (!$mimeType) {
                        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                        $mimeType = match($ext) {
                            'jpg', 'jpeg' => 'image/jpeg',
                            'png' => 'image/png',
                            'gif' => 'image/gif',
                            'webp' => 'image/webp',
                            default => 'image/jpeg',
                        };
                    }

                    $base64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                    Log::info('✅ Imagem carregada do R2', ['mime_type' => $mimeType]);
                    return $base64;
                }
            }

            // 2. Tenta via Storage local
            if (Storage::disk('public')->exists($path)) {
                $imageData = Storage::disk('public')->get($path);
                if ($imageData) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_buffer($finfo, $imageData);
                    finfo_close($finfo);
                    $base64 = 'data:' . ($mimeType ?: 'image/jpeg') . ';base64,' . base64_encode($imageData);
                    Log::info('✅ Imagem carregada do storage local');
                    return $base64;
                }
            }

            // 3. Fallback: URL externa via cURL
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                Log::info('🌐 Tentando baixar via URL', ['url' => $path]);
                
                $ch = curl_init($path);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ]);
                $imageData = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);

                if ($imageData !== false && $httpCode === 200) {
                    $mimeType = $contentType ?: 'image/jpeg';
                    $base64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                    Log::info('✅ Imagem baixada via URL', ['http_code' => $httpCode]);
                    return $base64;
                }
                
                Log::warning('⚠️ Falha ao baixar via URL', ['http_code' => $httpCode]);
            }

            // 4. Fallback: tenta como caminho absoluto do servidor
            if (file_exists($path)) {
                $imageData = file_get_contents($path);
                if ($imageData) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_buffer($finfo, $imageData);
                    finfo_close($finfo);
                    $base64 = 'data:' . ($mimeType ?: 'image/jpeg') . ';base64,' . base64_encode($imageData);
                    Log::info('✅ Imagem carregada do sistema de arquivos');
                    return $base64;
                }
            }

            Log::warning('❌ Não foi possível obter imagem por nenhum método', ['path' => $path]);
            return null;

        } catch (\Exception $e) {
            Log::error('❌ Erro ao obter imagem: ' . $e->getMessage(), ['path' => $path]);
            return null;
        }
    }

    /**
     * IMPRIMIR ABASTECIMENTO EXTERNO
     */
    public function printAbastecimentoExterno(Request $request, $id)
    {
        try {
            Log::info('🖨️ Imprimindo abastecimento externo', ['id' => $id]);

            // Autenticação
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
            $viagem = $abastecimento->viagem_id ? Viagem::find($abastecimento->viagem_id) : null;

            // Buscar logo da empresa
            $logo_empresa = null;
            if ($empresa && $empresa->logo_url) {
                $logo_empresa = $this->getImageBase64($empresa->logo_url);
                Log::info('📸 Logo processada', ['tem_logo' => !empty($logo_empresa)]);
            }

            $data = [
                'abastecimento' => $abastecimento,
                'viagem' => $viagem,
                'empresa' => $empresa,
                'logo_empresa' => $logo_empresa,
                'tipo' => $request->query('tipo', 'original'),
                'copia' => $request->query('copia', 'false'),
                
                'valor_formatado' => number_format($abastecimento->valor_total ?? 0, 2, ',', '.'),
                'valor_extenso' => $this->numeroPorExtenso($abastecimento->valor_total ?? 0),
                'quantidade_formatada' => number_format($abastecimento->quantidade ?? 0, 2, ',', '.'),
                'preco_formatado' => number_format($abastecimento->preco_unitario ?? 0, 2, ',', '.'),
                
                'data_abastecimento' => $abastecimento->data_abastecimento ? date('d/m/Y', strtotime($abastecimento->data_abastecimento)) : date('d/m/Y'),
                'data_extenso' => $abastecimento->data_abastecimento ? $this->dataPorExtenso($abastecimento->data_abastecimento) : '',
                'data_emissao' => now()->format('d/m/Y H:i:s'),
                'current_date' => now()->format('d/m/Y H:i:s'),
                
                'usuario' => $user->name ?? 'Sistema',
            ];

            // Debug mode
            if ($request->query('debug') === 'true') {
                $html = view('pdf.abastecimento-externo', $data)->render();
                return response($html)->header('Content-Type', 'text/html');
            }

            $pdf = Pdf::loadView('pdf.abastecimento-externo', $data);
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions([
                'defaultFont' => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'isPhpEnabled' => true,
                'dpi' => 150,
            ]);

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

            // Autenticação
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
                $logo_empresa = $this->getImageBase64($empresa->logo_url);
                Log::info('📸 Logo processada', ['tem_logo' => !empty($logo_empresa)]);
            }

            $data = [
                'abastecimento' => $abastecimento,
                'empresa' => $empresa,
                'logo_empresa' => $logo_empresa,
                'tipo' => $request->query('tipo', 'original'),
                'copia' => $request->query('copia', 'false'),
                
                'quantidade_formatada' => number_format($abastecimento->quantidade ?? 0, 2, ',', '.'),
                'data_abastecimento' => $abastecimento->data_abastecimento ? date('d/m/Y', strtotime($abastecimento->data_abastecimento)) : date('d/m/Y'),
                'hora_abastecimento' => $abastecimento->hora_abastecimento ?? '',
                'data_extenso' => $abastecimento->data_abastecimento ? $this->dataPorExtenso($abastecimento->data_abastecimento) : '',
                'data_emissao' => now()->format('d/m/Y H:i:s'),
                'current_date' => now()->format('d/m/Y H:i:s'),
                'usuario' => $user->name ?? 'Sistema',
            ];

            // Debug mode
            if ($request->query('debug') === 'true') {
                $html = view('pdf.abastecimento-interno', $data)->render();
                return response($html)->header('Content-Type', 'text/html');
            }

            $pdf = Pdf::loadView('pdf.abastecimento-interno', $data);
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions([
                'defaultFont' => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'isPhpEnabled' => true,
                'dpi' => 150,
            ]);

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
                $logo_empresa = $this->getImageBase64($empresa->logo_url);
            }

            $data = [
                'pedido' => $pedido,
                'empresa' => $empresa,
                'logo_empresa' => $logo_empresa,
                'tipo' => $request->query('tipo', 'original'),
                'copia' => $request->query('copia', 'false'),
                
                'valor_formatado' => number_format($pedido->valor_total ?? 0, 2, ',', '.'),
                'valor_extenso' => $this->numeroPorExtenso($pedido->valor_total ?? 0),
                'quantidade_formatada' => number_format($pedido->quantidade ?? 0, 2, ',', '.'),
                'preco_formatado' => number_format($pedido->preco_unitario ?? 0, 2, ',', '.'),
                
                'data_pedido' => $pedido->data_pedido ? date('d/m/Y', strtotime($pedido->data_pedido)) : '--/--/----',
                'data_entrega_prevista' => $pedido->data_entrega_prevista ? date('d/m/Y', strtotime($pedido->data_entrega_prevista)) : '--/--/----',
                'data_entrega_real' => $pedido->data_entrega_real ? date('d/m/Y', strtotime($pedido->data_entrega_real)) : null,
                'data_emissao' => now()->format('d/m/Y H:i:s'),
                'current_date' => now()->format('d/m/Y H:i:s'),
                'usuario' => $user->name ?? 'Sistema',
            ];

            if ($request->query('debug') === 'true') {
                $html = view('pdf.pedido-compra', $data)->render();
                return response($html)->header('Content-Type', 'text/html');
            }

            $pdf = Pdf::loadView('pdf.pedido-compra', $data);
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions([
                'defaultFont' => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'isPhpEnabled' => true,
                'dpi' => 150,
            ]);

            $filename = 'PEDIDO_' . ($pedido->numero ?? $pedido->id) . '_' . date('Ymd_His') . '.pdf';

            if ($request->query('download') === '1') {
                return $pdf->download($filename);
            }

            return $pdf->stream($filename);

        } catch (\Exception $e) {
            Log::error('❌ Erro: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}