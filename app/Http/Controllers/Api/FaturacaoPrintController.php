<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotaFiscal;
use App\Models\OrdemFaturacao;
use App\Models\Empresa;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf;

class FaturacaoPrintController extends Controller
{
    // =========================================================================
    // UTILITÁRIOS
    // =========================================================================

    /**
     * Converte número para extenso em português (meticais).
     */
    private function numeroPorExtenso($valor)
    {
        $valor = floatval($valor);
        if ($valor == 0) return 'zero meticais';

        $partes    = explode('.', number_format($valor, 2, '.', ''));
        $reais     = intval($partes[0]);
        $centavos  = intval($partes[1] ?? 0);

        $unidades  = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];
        $dezenas   = ['', 'dez', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
        $centenas  = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];
        $especiais = [
            11 => 'onze',     12 => 'doze',      13 => 'treze',    14 => 'quatorze',
            15 => 'quinze',   16 => 'dezesseis',  17 => 'dezessete',
            18 => 'dezoito',  19 => 'dezenove',
        ];

        $extenso = '';

        if ($reais >= 1000) {
            $milhares = floor($reais / 1000);
            $reais    = $reais % 1000;

            $extenso .= $milhares == 1
                ? 'mil'
                : $this->numeroPorExtensoParcial($milhares, $unidades, $dezenas, $centenas, $especiais) . ' mil';

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
     * Auxiliar recursivo para numeroPorExtenso.
     */
    private function numeroPorExtensoParcial($num, $u, $d, $c, $e)
    {
        if ($num < 10)                     return $u[$num];
        if ($num >= 11 && $num <= 19)      return $e[$num];
        if ($num < 100) {
            $dez = floor($num / 10);
            $uni = $num % 10;
            return $d[$dez] . ($uni > 0 ? ' e ' . $u[$uni] : '');
        }
        if ($num == 100) return 'cem';
        $cen   = floor($num / 100);
        $resto = $num % 100;
        return $c[$cen] . ($resto > 0 ? ' e ' . $this->numeroPorExtensoParcial($resto, $u, $d, $c, $e) : '');
    }

    /**
     * Formata data por extenso em português.
     */
    private function dataPorExtenso($data)
    {
        $meses = [
            'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
            'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
        ];
        $t = strtotime($data);
        return date('d', $t) . " de {$meses[date('n', $t) - 1]} de " . date('Y', $t);
    }

    /**
     * Busca dados completos do cliente pelo nome ou ID
     */
    private function buscarDadosCliente($tenantId, $clienteNome = null, $clienteId = null): ?array
    {
        if (!$clienteNome && !$clienteId) {
            return null;
        }

        $query = Cliente::where('tenant_id', $tenantId);

        if ($clienteId) {
            $query->where('id', $clienteId);
        } elseif ($clienteNome) {
            $query->where('nome_empresa', 'like', "%{$clienteNome}%");
        }

        $cliente = $query->first();

        if (!$cliente) {
            return [
                'nome' => $clienteNome ?? '—',
                'endereco' => null,
                'nuit' => null,
                'contacto' => null,
                'telefone' => null,
                'email' => null,
                'pais' => null,
                'tipo' => null,
            ];
        }

        return [
            'nome' => $cliente->nome_empresa,
            'endereco' => $cliente->endereco,
            'nuit' => $cliente->nuit_nif,
            'contacto' => $cliente->pessoa_contato,
            'telefone' => $cliente->telefone,
            'email' => $cliente->email,
            'pais' => $cliente->pais,
            'tipo' => $cliente->tipo_cliente,
            'iva' => $cliente->iva,
        ];
    }

    /**
     * Converte imagem do Cloudflare R2 para base64.
     */
    private function getImageBase64($r2Path)
    {
        if (empty($r2Path)) {
            return null;
        }

        try {
            // 1. Tenta via Storage disk R2
            if (Storage::disk('r2')->exists($r2Path)) {
                $imageData = Storage::disk('r2')->get($r2Path);

                if ($imageData) {
                    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_buffer($finfo, $imageData);
                    finfo_close($finfo);

                    if (!$mimeType) {
                        $ext      = strtolower(pathinfo($r2Path, PATHINFO_EXTENSION));
                        $mimeType = match ($ext) {
                            'jpg', 'jpeg' => 'image/jpeg',
                            'png'         => 'image/png',
                            'gif'         => 'image/gif',
                            'webp'        => 'image/webp',
                            default       => 'image/jpeg',
                        };
                    }

                    return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                }
            }

            // 2. Fallback: tenta baixar via URL directa
            if (filter_var($r2Path, FILTER_VALIDATE_URL)) {
                $ch = curl_init($r2Path);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT        => 10,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_USERAGENT      => 'Mozilla/5.0',
                ]);
                $imageData = curl_exec($ch);
                $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($imageData !== false && $httpCode === 200) {
                    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
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
     * Autentica o utilizador via token de query-string (Sanctum).
     */
    private function authenticateFromToken(Request $request): bool
    {
        $token = $request->query('token');
        if ($token) {
            $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if ($pat) Auth::login($pat->tokenable);
        }
        return Auth::check();
    }

    // =========================================================================
    // ENDPOINTS
    // =========================================================================

    /**
     * IMPRIMIR NOTA FISCAL (Crédito ou Débito)
     */
    public function printNota(Request $request, $id)
    {
        try {
            if (!$this->authenticateFromToken($request)) {
                return response()->json(['error' => 'Não autenticado'], 401);
            }

            $user = Auth::user();
            $tipo = $request->query('tipo', 'credito');

            $nota = NotaFiscal::with(['ordem', 'ordem.viagem'])->where('tipo', $tipo)->find($id);

            if (!$nota) {
                return response()->json(['error' => 'Nota não encontrada'], 404);
            }

            if ($nota->tenant_id != $user->tenant_id) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $empresa    = Empresa::where('tenant_id', $user->tenant_id)->first();
            $logoBase64 = null;

            if ($empresa && $empresa->logo_url) {
                $logoBase64 = $this->getImageBase64($empresa->logo_url);
            }

            // Buscar dados completos do cliente
            $clienteDados = $this->buscarDadosCliente(
                $user->tenant_id,
                $nota->cliente,
                $nota->cliente_id ?? null
            );

            $tipo_info = [
                'titulo'    => $tipo == 'credito' ? 'Nota de Crédito' : 'Nota de Débito',
                'cor'       => '#013334',
                'cor_fundo' => '#e8f0f0',
                'sinal'     => $tipo == 'credito' ? '-' : '',
            ];

            $data = [
                'nota'             => $nota,
                'empresa'          => $empresa,
                'cliente'          => $clienteDados,
                'tipo_info'        => $tipo_info,
                'numero_formatado' => $nota->numero,
                'logo_empresa'     => $logoBase64,
                'valor_formatado'  => number_format($nota->valor, 2, ',', '.'),
                'valor_extenso'    => $this->numeroPorExtenso($nota->valor),
                'data_emissao'     => date('d/m/Y', strtotime($nota->data)),
                'data_extenso'     => $this->dataPorExtenso($nota->data),
                'criadoPor'        => $user->name ?? 'Sistema',
                'current_date'     => now()->format('d/m/Y H:i:s'),
            ];

            if ($request->query('debug') === 'true') {
                return response(View::make('pdf.nota-fiscal', $data)->render())
                    ->header('Content-Type', 'text/html');
            }

            $html     = View::make('pdf.nota-fiscal', $data)->render();
            $filename = ($tipo == 'credito' ? 'NC' : 'ND') . '_' . $nota->numero . '_' . date('Ymd_His') . '.pdf';

            $pdf = Pdf::loadHTML($html);
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions([
                'defaultFont'          => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => true,
                'isPhpEnabled'         => true,
                'dpi'                  => 150,
            ]);

            if ($request->query('download') === '1' || $request->query('download') === 'true') {
                return $pdf->download($filename);
            }

            return $pdf->stream($filename);

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
            if (!$this->authenticateFromToken($request)) {
                return response()->json(['error' => 'Não autenticado'], 401);
            }

            $user  = Auth::user();
            $ordem = OrdemFaturacao::with(['viagem', 'notas'])->find($id);

            if (!$ordem) {
                return response()->json(['error' => 'Ordem não encontrada'], 404);
            }

            if ($ordem->tenant_id != $user->tenant_id) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }

            $empresa    = Empresa::where('tenant_id', $user->tenant_id)->first();
            $logoBase64 = null;

            if ($empresa && $empresa->logo_url) {
                $logoBase64 = $this->getImageBase64($empresa->logo_url);
            }

            // Buscar dados completos do cliente
            $clienteDados = $this->buscarDadosCliente(
                $user->tenant_id,
                $ordem->cliente ?? ($ordem->viagem->customer_name ?? null),
                $ordem->cliente_id ?? null
            );

            $totalCredito = $ordem->notas->where('tipo', 'credito')->sum('valor');
            $totalDebito  = $ordem->notas->where('tipo', 'debito')->sum('valor');
            $saldo        = $ordem->valor + $totalDebito - $totalCredito;

            $statusMap = [
                'pendente'   => 'PENDENTE',
                'processado' => 'FATURADO',
                'cancelado'  => 'CANCELADO',
            ];

            $data = [
                'ordem'              => $ordem,
                'viagem'             => $ordem->viagem,
                'notas'              => $ordem->notas,
                'empresa'            => $empresa,
                'cliente'            => $clienteDados,
                'logo_empresa'       => $logoBase64,
                'status'             => $statusMap[$ordem->status] ?? strtoupper($ordem->status),
                'servicos_formatado' => number_format($ordem->valor, 2, ',', '.'),
                'creditos_formatado' => number_format($totalCredito, 2, ',', '.'),
                'debitos_formatado'  => number_format($totalDebito, 2, ',', '.'),
                'saldo_formatado'    => number_format($saldo, 2, ',', '.'),
                'valor_extenso'      => $this->numeroPorExtenso($saldo),
                'totais' => [
                    'servicos' => $ordem->valor,
                    'creditos' => $totalCredito,
                    'debitos'  => $totalDebito,
                    'saldo'    => $saldo,
                ],
                'data_emissao'      => $ordem->created_at ? $ordem->created_at->format('d/m/Y') : date('d/m/Y'),
                'data_extenso'      => $this->dataPorExtenso($ordem->created_at ? $ordem->created_at->format('Y-m-d') : date('Y-m-d')),
                'criadoPor'         => $user->name ?? 'Sistema',
                'current_date'      => now()->format('d/m/Y H:i:s'),
            ];

            if ($request->query('debug') === 'true') {
                return response(View::make('pdf.faturacao-ordem', $data)->render())
                    ->header('Content-Type', 'text/html');
            }

            $html     = View::make('pdf.faturacao-ordem', $data)->render();
            $filename = 'ORDEM_' . $ordem->codigo . '_' . date('Ymd_His') . '.pdf';

            $pdf = Pdf::loadHTML($html);
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions([
                'defaultFont'          => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => true,
                'isPhpEnabled'         => true,
                'dpi'                  => 150,
            ]);

            if ($request->query('download') === '1' || $request->query('download') === 'true') {
                return $pdf->download($filename);
            }

            return $pdf->stream($filename);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao imprimir ordem: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}