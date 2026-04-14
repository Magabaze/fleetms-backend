<?php
// app/Http/Controllers/Api/CaixaPrintController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Viagem;
use App\Models\DriverExpense;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class CaixaPrintController extends Controller
{
    /**
     * CONVERTE IMAGEM PARA BASE64 DE FORMA ROBUSTA
     */
    private function getImageBase64($path)
    {
        if (empty($path)) {
            return null;
        }

        try {
            if (strpos($path, 'data:image') === 0) {
                return $path;
            }

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
                    
                    return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                }
            }

            // 2. Fallback: URL externa via cURL
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                $ch = curl_init($path);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_USERAGENT => 'Mozilla/5.0',
                ]);
                $imageData = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($imageData !== false && $httpCode === 200) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_buffer($finfo, $imageData) ?: 'image/jpeg';
                    finfo_close($finfo);
                    return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Erro ao obter imagem: ' . $e->getMessage());
            return null;
        }
    }

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

        if ($reais >= 1000000) {
            $milhoes = floor($reais / 1000000);
            $reais = $reais % 1000000;
            $extensoMilhoes = $this->numeroPorExtensoParcial($milhoes, $unidades, $dezenas, $centenas, $especiais);
            $extenso .= $milhoes == 1 ? 'um milhão' : $extensoMilhoes . ' milhões';
            if ($reais > 0) $extenso .= ', ';
        }

        if ($reais >= 1000) {
            $milhares = floor($reais / 1000);
            $reais = $reais % 1000;
            $extenso .= $milhares == 1 ? 'mil' : $this->numeroPorExtensoParcial($milhares, $unidades, $dezenas, $centenas, $especiais) . ' mil';
            if ($reais > 0) $extenso .= ' e ';
        }

        if ($reais > 0) {
            $extenso .= $this->numeroPorExtensoParcial($reais, $unidades, $dezenas, $centenas, $especiais);
        }

        $extenso .= $valor >= 2 ? ' meticais' : ' metical';

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
     * IMPRIMIR JUSTIFICATIVO DE VIAGEM
     */
    public function printJustificativo(Request $request, $viagemId)
    {
        try {
            Log::info('🖨️ Imprimindo justificativo de viagem', ['viagem_id' => $viagemId]);

            $token = $request->query('token');
            if ($token) {
                $user = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($user) Auth::login($user->tokenable);
            }

            if (!Auth::check()) {
                return response()->json(['error' => 'Não autenticado'], 401);
            }

            $user = Auth::user();

            $viagem = Viagem::where('tenant_id', $user->tenant_id)->find($viagemId);
            if (!$viagem) {
                return response()->json(['error' => 'Viagem não encontrada'], 404);
            }

            $despesas = DriverExpense::where('viagem_id', $viagemId)
                ->where('is_active', true)
                ->orderBy('created_at')
                ->get();

            $empresa = Empresa::where('tenant_id', $user->tenant_id)->first();

            // Usar o método unificado de imagem
            $logo_empresa = null;
            if ($empresa && $empresa->logo_url) {
                $logo_empresa = $this->getImageBase64($empresa->logo_url);
            }

            $despesasPendentes = $despesas->where('status', 'paid');
            $despesasJustificadas = $despesas->where('status', 'settled');

            $totais = [];
            foreach ($despesas as $despesa) {
                $moeda = $despesa->currency ?? 'MZN';
                if (!isset($totais[$moeda])) {
                    $totais[$moeda] = 0;
                }
                $totais[$moeda] += $despesa->amount ?? 0;
            }

            $totaisFormatados = [];
            $valorExtenso = '';
            foreach ($totais as $moeda => $valor) {
                $totaisFormatados[$moeda] = number_format($valor, 2, ',', '.');
                if (empty($valorExtenso)) {
                    $valorExtenso = $this->numeroPorExtenso($valor);
                }
            }

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
                'totaisFormatados' => $totaisFormatados,
                'valorExtenso' => $valorExtenso,
                'data_emissao' => now()->format('d/m/Y H:i:s'),
                'current_date' => now()->format('d/m/Y H:i:s'),
                'usuario' => $user->name ?? 'Sistema',
            ];

            if ($request->query('debug') === 'true') {
                $html = view('pdf.justificativo-viagem', $data)->render();
                return response($html)->header('Content-Type', 'text/html');
            }

            $pdf = Pdf::loadView('pdf.justificativo-viagem', $data);
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions([
                'defaultFont' => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'isPhpEnabled' => true,
                'dpi' => 150,
            ]);

            $filename = 'JUSTIFICATIVO_' . $viagem->trip_number . '_' . date('Ymd_His') . '.pdf';

            if ($request->query('download') === '1') {
                return $pdf->download($filename);
            }

            return $pdf->stream($filename);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao gerar justificativo: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}