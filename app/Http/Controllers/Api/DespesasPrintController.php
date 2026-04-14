<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Viagem;
use App\Models\DriverExpense;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf;

class DespesasPrintController extends Controller
{
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
     * BUSCAR VIAGEM COM SEGURANÇA
     */
    private function buscarViagemComSeguranca(int $viagemId, ?string $tenantId): ?Viagem
    {
        $query = Viagem::where('id', $viagemId);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        return $query->first();
    }

    /**
     * IMPRIMIR REFORÇO DE VALORES (DESPESAS)
     */
    public function printDespesas(Request $request, $viagemId)
    {
        try {
            Log::info('🖨️ Imprimindo Reforço de Valores', ['viagem_id' => $viagemId]);

            // ── AUTENTICAÇÃO ──────────────────────────────────────────
            $token = $request->query('token');
            if ($token) {
                $user = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($user) Auth::login($user->tokenable);
            }

            if (!Auth::check()) {
                return response()->json(['error' => 'Não autenticado'], 401);
            }

            $user = Auth::user();

            // ── VIAGEM ────────────────────────────────────────────────
            $viagem = $this->buscarViagemComSeguranca($viagemId, $user->tenant_id);
            if (!$viagem) {
                return response()->json(['error' => 'Viagem não encontrada'], 404);
            }

            // ── EMPRESA ───────────────────────────────────────────────
            $empresa = Empresa::where('tenant_id', $user->tenant_id)->first();

            // ── PARÂMETROS ────────────────────────────────────────────
            $despesaIds = $request->isMethod('post')
                ? $request->input('despesa_ids', [])
                : json_decode($request->query('despesa_ids', '[]'), true) ?? [];

            $tipo = $request->input('tipo', $request->query('tipo', 'original'));

            if (empty($despesaIds)) {
                return response()->json(['error' => 'Nenhuma despesa selecionada'], 422);
            }

            // ── DESPESAS ──────────────────────────────────────────────
            $despesas = DriverExpense::where('viagem_id', $viagemId)
                ->whereIn('id', $despesaIds)
                ->where('is_active', true)
                ->with(['tipoDespesa'])
                ->orderBy('currency')
                ->orderBy('id')
                ->get();

            if ($despesas->isEmpty()) {
                return response()->json(['error' => 'Nenhuma despesa válida encontrada'], 422);
            }

            // ── RESUMO POR MOEDA ─────────────────────────────────────
            $resumoPorMoeda = [];
            foreach ($despesas as $d) {
                $moeda = $d->currency ?? 'MZN';
                if (!isset($resumoPorMoeda[$moeda])) {
                    $resumoPorMoeda[$moeda] = ['total' => 0, 'quantidade' => 0];
                }
                $resumoPorMoeda[$moeda]['total'] += floatval($d->amount ?? 0);
                $resumoPorMoeda[$moeda]['quantidade']++;
            }
            arsort($resumoPorMoeda);

            // ── LOGO ──────────────────────────────────────────────────
            $logoEmpresaBase64 = null;
            if ($empresa && $empresa->logo_url) {
                $logoEmpresaBase64 = $this->getImageBase64($empresa->logo_url);
                Log::info('📸 Logo processada', ['tem_logo' => !empty($logoEmpresaBase64)]);
            }

            // ── DADOS PARA A VIEW ─────────────────────────────────────
            $data = [
                'viagem'           => $viagem,
                'empresa'          => $empresa,
                'despesas'         => $despesas,
                'resumo_por_moeda' => $resumoPorMoeda,
                'tipo'             => $tipo,
                'logo_empresa'     => $logoEmpresaBase64,
                'usuario'          => $user->name ?? 'Sistema',
                'data_emissao'     => now()->format('d/m/Y H:i:s'),
                'current_date'     => now()->format('d/m/Y H:i:s'),
            ];

            // ── DEBUG ─────────────────────────────────────────────────
            if ($request->query('debug') === 'true') {
                if (!View::exists('pdf.reforco-valores')) {
                    return response()->json(['error' => 'View pdf.reforco-valores não encontrada'], 500);
                }
                $html = View::make('pdf.reforco-valores', $data)->render();
                return response($html)->header('Content-Type', 'text/html');
            }

            // ── GERAR PDF COM DOMPDF ──────────────────────────────────
            if (!View::exists('pdf.reforco-valores')) {
                throw new \Exception('View pdf.reforco-valores não encontrada.');
            }

            $html = View::make('pdf.reforco-valores', $data)->render();
            
            $pdf = Pdf::loadHTML($html);
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions([
                'defaultFont'          => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => true,
                'isPhpEnabled'         => true,
                'dpi'                  => 150,
            ]);

            $filename = 'REFORCO_VALORES_' . ($viagem->trip_number ?? 'VIAGEM') . '_' . strtoupper($tipo) . '_' . date('Ymd_His') . '.pdf';

            if ($request->query('download') === '1' || $request->query('download') === 'true') {
                return $pdf->download($filename);
            }

            return $pdf->stream($filename);

        } catch (\Exception $e) {
            Log::error('❌ Erro no DespesasPrintController', [
                'erro' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'error'   => 'Erro ao gerar PDF',
                'message' => config('app.debug') ? $e->getMessage() : 'Erro interno',
            ], 500);
        }
    }
}