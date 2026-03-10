<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Viagem;
use App\Models\DriverExpense; // ✅ CORRIGIDO: Usando DriverExpense em vez de ViagemDespesa
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Barryvdh\Snappy\Facades\SnappyPdf;

class DespesasPrintController extends Controller
{
    /**
     * IMPRIMIR REFORÇO DE VALORES COM SNAPPY PDF
     * 
     * POST /api/viagens/{viagemId}/print-despesas
     * GET  /api/viagens/{viagemId}/print-despesas?token=xxx
     * 
     * @param Request $request
     * @param int $viagemId
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function printDespesas(Request $request, $viagemId)
    {
        try {
            // ============================================
            // ✅ PASSO 1: AUTENTICAÇÃO - VIA TOKEN NA URL
            // ============================================
            Log::info('🖨️ Iniciando geração de PDF Reforço de Valores', [
                'viagem_id' => $viagemId,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'has_token_query' => $request->has('token') ? 'SIM' : 'NÃO',
                'has_token_header' => $request->bearerToken() ? 'SIM' : 'NÃO'
            ]);

            // ✅ AUTENTICAÇÃO VIA QUERY STRING (token na URL) - IGUAL AO PRINTCONTROLLER
            $token = $request->query('token');
            if ($token) {
                try {
                    $user = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                    if ($user) {
                        Auth::login($user->tokenable);
                        Log::info('✅ Autenticado via token na URL (query string)', [
                            'user_id' => Auth::id(),
                            'user_name' => Auth::user()?->name
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('⚠️ Token inválido na query string', [
                        'error' => $e->getMessage(),
                        'token_preview' => substr($token, 0, 10) . '...'
                    ]);
                }
            }

            // ✅ VERIFICAR SE ESTÁ AUTENTICADO
            if (!Auth::check()) {
                Log::warning('⚠️ Usuário não autenticado');
                return response()->json([
                    'success' => false,
                    'error' => 'Não autenticado',
                    'message' => 'Token de autenticação não fornecido ou inválido'
                ], 401);
            }

            $user = Auth::user();

            // ============================================
            // ✅ PASSO 2: BUSCAR VIAGEM COM VALIDAÇÃO DE SEGURANÇA
            // ============================================
            $viagem = $this->buscarViagemComSeguranca($viagemId, $user?->tenant_id);
            
            if (!$viagem) {
                Log::warning('⚠️ Viagem não encontrada ou acesso negado', [
                    'viagem_id' => $viagemId,
                    'user_id' => $user?->id,
                    'tenant_id' => $user?->tenant_id
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Viagem não encontrada'
                ], 404);
            }

            Log::info('✅ Viagem encontrada', [
                'viagem_id' => $viagem->id,
                'trip_number' => $viagem->trip_number,
                'driver' => $viagem->driver
            ]);

            // ============================================
            // ✅ PASSO 3: BUSCAR EMPRESA
            // ============================================
            $empresa = $this->buscarEmpresa($viagem->tenant_id);
            
            // ============================================
            // ✅ PASSO 4: VALIDAR ENTRADA (SUPORTA GET E POST)
            // ============================================
            $despesaIds = [];
            
            if ($request->isMethod('post')) {
                // POST: recebe do body
                $despesaIds = $request->input('despesa_ids', []);
            } else {
                // GET: recebe da query string
                $despesaIdsJson = $request->query('despesa_ids', '[]');
                $despesaIds = json_decode($despesaIdsJson, true) ?? [];
            }
            
            $tipo = $request->input('tipo', $request->query('tipo', 'original'));
            
            if (empty($despesaIds)) {
                Log::warning('⚠️ Nenhuma despesa selecionada', [
                    'viagem_id' => $viagemId,
                    'user_id' => $user?->id
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Nenhuma despesa selecionada'
                ], 422);
            }

            if (!in_array($tipo, ['original', 'duplicate'])) {
                $tipo = 'original';
            }

            Log::info('📋 Parâmetros de impressão', [
                'despesa_ids_count' => count($despesaIds),
                'despesa_ids' => $despesaIds,
                'tipo' => $tipo,
                'method' => $request->method()
            ]);

            // ============================================
            // ✅ PASSO 5: BUSCAR DESPESAS COM VALIDAÇÃO - USANDO DriverExpense
            // ============================================
            $despesas = $this->buscarDespesasComValidacao($viagemId, $despesaIds);
            
            if (count($despesas) === 0) {
                Log::warning('⚠️ Nenhuma despesa válida encontrada', [
                    'viagem_id' => $viagemId,
                    'despesa_ids_solicitadas' => count($despesaIds)
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Nenhuma despesa válida encontrada'
                ], 422);
            }

            if (count($despesas) !== count($despesaIds)) {
                Log::warning('⚠️ Nem todas as despesas foram encontradas', [
                    'solicitadas' => count($despesaIds),
                    'encontradas' => count($despesas),
                    'nao_encontradas' => array_diff($despesaIds, array_column($despesas, 'id'))
                ]);
            }

            // ============================================
            // ✅ PASSO 6: AGRUPAR DESPESAS POR MOEDA
            // ============================================
            $agrupadas = $this->agruparDespesasPorMoeda($despesas);

            // ============================================
            // ✅ PASSO 7: BUSCAR LOGO DA EMPRESA
            // ============================================
            $logoEmpresaBase64 = null;
            if ($empresa && $empresa->logo_url) {
                $logoEmpresaBase64 = $this->getImageBase64($empresa->logo_url);
                Log::info('🖼️ Logo da empresa', [
                    'tem_logo' => !empty($logoEmpresaBase64),
                    'tamanho' => $logoEmpresaBase64 ? strlen($logoEmpresaBase64) : 0
                ]);
            }

            // ============================================
            // ✅ PASSO 8: PREPARAR DADOS PARA A VIEW
            // ============================================
            $data = [
                'viagem' => $viagem,
                'empresa' => $empresa,
                'despesas_agrupadas' => $agrupadas,
                'tipo' => $tipo,
                'logo_empresa' => $logoEmpresaBase64,
                'usuario' => $user?->name ?? 'Sistema',
                'data_emissao' => now()->format('d/m/Y H:i:s'),
                'total_moedas' => count($agrupadas)
            ];

            // ============================================
            // ✅ PASSO 9: DEBUG MODE (RETORNA HTML)
            // ============================================
            if ($request->query('debug') === 'true' || $request->query('html') === 'true') {
                Log::info('🐛 Debug mode ativado - renderizando HTML');
                
                try {
                    $html = View::make('pdf.reforco-valores', $data)->render();
                    
                    Log::info('✅ View renderizada com sucesso', [
                        'view' => 'pdf.reforco-valores',
                        'html_length' => strlen($html)
                    ]);
                    
                    return response($html)->header('Content-Type', 'text/html; charset=utf-8');
                } catch (\Exception $e) {
                    Log::error('❌ Erro ao renderizar view', [
                        'view' => 'pdf.reforco-valores',
                        'erro' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'error' => 'Erro ao renderizar HTML',
                        'message' => $e->getMessage()
                    ], 500);
                }
            }

            // ============================================
            // ✅ PASSO 10: GERAR PDF COM SNAPPY
            // ============================================
            return $this->generatePdf($data, $viagem, $tipo, $request->query('download') === '1');

        } catch (\Exception $e) {
            Log::error('❌ Erro no DespesasPrintController::printDespesas', [
                'viagem_id' => $viagemId ?? 'N/A',
                'user_id' => Auth::id() ?? 'N/A',
                'erro' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Erro ao gerar PDF',
                'message' => config('app.debug') ? $e->getMessage() : 'Erro interno na geração do documento'
            ], 500);
        }
    }

    /**
     * Buscar viagem com validação de segurança
     */
    private function buscarViagemComSeguranca(int $viagemId, ?string $tenantId): ?Viagem
    {
        $query = Viagem::where('id', $viagemId);
        
        // Se o usuário tem tenant, filtrar por tenant
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        
        return $query->first();
    }

    /**
     * Buscar despesas com validação de viagem - USANDO DriverExpense
     */
    private function buscarDespesasComValidacao(int $viagemId, array $despesaIds): array
    {
        return DriverExpense::where('viagem_id', $viagemId) // ✅ CORRIGIDO: DriverExpense
            ->whereIn('id', $despesaIds)
            ->where('is_active', true)
            ->with('usuario')
            ->get()
            ->toArray();
    }

    /**
     * Buscar empresa por tenant_id
     */
    private function buscarEmpresa(?string $tenantId): ?Empresa
    {
        if (!$tenantId) {
            return null;
        }
        
        return Empresa::where('tenant_id', $tenantId)->first();
    }

    /**
     * Agrupar despesas por moeda
     */
    private function agruparDespesasPorMoeda(array $despesas): array
    {
        $agrupadas = [];

        foreach ($despesas as $despesa) {
            $moeda = $despesa['currency'] ?? 'MZN';
            
            if (!isset($agrupadas[$moeda])) {
                $agrupadas[$moeda] = [
                    'total' => 0,
                    'descricoes' => [],
                    'quantidade' => 0
                ];
            }

            $agrupadas[$moeda]['total'] += floatval($despesa['amount']);
            $agrupadas[$moeda]['quantidade']++;

            if (!empty($despesa['payment_description']) && 
                !in_array($despesa['payment_description'], $agrupadas[$moeda]['descricoes'])) {
                $agrupadas[$moeda]['descricoes'][] = $despesa['payment_description'];
            }
        }

        // Ordenar por valor total (decrescente)
        uasort($agrupadas, fn($a, $b) => $b['total'] <=> $a['total']);

        return $agrupadas;
    }

    /**
     * Gerar PDF com Snappy
     */
    private function generatePdf(array $data, Viagem $viagem, string $tipo, bool $download = false)
    {
        try {
            Log::info('📄 Gerando PDF com Snappy', [
                'viagem_id' => $viagem->id,
                'tipo' => $tipo,
                'download' => $download,
                'total_moedas' => count($data['despesas_agrupadas'] ?? [])
            ]);

            // ✅ Verificar se a view existe
            if (!View::exists('pdf.reforco-valores')) {
                Log::error('❌ View pdf.reforco-valores não encontrada');
                throw new \Exception('View de PDF não encontrada. Crie o arquivo em resources/views/pdf/reforco-valores.blade.php');
            }

            // ✅ Carregar view e gerar PDF
            $pdf = SnappyPdf::loadView('pdf.reforco-valores', $data)
                ->setOption('enable-local-file-access', true)
                ->setOption('page-size', 'A4')
                ->setOption('orientation', 'portrait')
                ->setOption('margin-top', '10')
                ->setOption('margin-right', '10')
                ->setOption('margin-bottom', '10')
                ->setOption('margin-left', '10')
                ->setOption('encoding', 'UTF-8')
                ->setOption('dpi', 150)
                ->setOption('disable-smart-shrinking', false)
                ->setOption('zoom', 1.0)
                ->setOption('quiet', true);

            // ✅ Gerar nome do arquivo
            $filename = sprintf(
                'REFORCO_VALORES_%s_%s_%s.pdf',
                $viagem->trip_number ?? $viagem->tripNumber ?? 'VIAGEM',
                strtoupper($tipo),
                date('Ymd_His')
            );

            Log::info('✅ PDF gerado com sucesso', [
                'filename' => $filename,
                'viagem_id' => $viagem->id
            ]);

            // ✅ Retornar PDF
            if ($download) {
                return $pdf->download($filename);
            }

            return $pdf->stream($filename);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao gerar PDF com Snappy', [
                'viagem_id' => $viagem->id,
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Erro ao gerar PDF com Snappy',
                'message' => config('app.debug') ? $e->getMessage() : 'Erro na geração do PDF'
            ], 500);
        }
    }

    /**
     * Obter imagem em base64 (de R2 ou URL)
     */
    private function getImageBase64(?string $r2Path): ?string
    {
        if (empty($r2Path)) {
            return null;
        }

        try {
            // ✅ Tentar obter do R2
            if (Storage::disk('r2')->exists($r2Path)) {
                $imageData = Storage::disk('r2')->get($r2Path);
                if (!$imageData) {
                    Log::warning('⚠️ Imagem vazia no R2', ['path' => $r2Path]);
                    return null;
                }

                $mimeType = $this->detectMimeType($imageData, $r2Path);
                $base64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                
                Log::info('✅ Imagem carregada do R2', [
                    'path' => $r2Path,
                    'mime' => $mimeType,
                    'size' => strlen($imageData)
                ]);
                
                return $base64;
            }

            // ✅ Tentar como URL
            if (filter_var($r2Path, FILTER_VALIDATE_URL)) {
                return $this->getImageFromUrl($r2Path);
            }

            Log::warning('⚠️ Arquivo não encontrado no R2 e não é URL válida', [
                'path' => $r2Path
            ]);
            
            return null;

        } catch (\Exception $e) {
            Log::warning('⚠️ Erro ao obter imagem para PDF', [
                'path' => $r2Path,
                'erro' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Detectar MIME type da imagem
     */
    private function detectMimeType(string $imageData, string $path): string
    {
        // ✅ Tentar com finfo
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_buffer($finfo, $imageData);
            finfo_close($finfo);

            if ($mimeType && strpos($mimeType, 'image/') === 0) {
                return $mimeType;
            }
        }

        // ✅ Fallback: verificar extensão
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg'
        };
    }

    /**
     * Obter imagem de uma URL externa
     */
    private function getImageFromUrl(string $url): ?string
    {
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]);

            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($imageData && $httpCode === 200) {
                $mimeType = $this->detectMimeType($imageData, $url);
                $base64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                
                Log::info('✅ Imagem carregada via URL', [
                    'url' => $url,
                    'http_code' => $httpCode,
                    'mime' => $mimeType,
                    'size' => strlen($imageData)
                ]);
                
                return $base64;
            }

            Log::warning('⚠️ Falha ao carregar imagem via URL', [
                'url' => $url,
                'http_code' => $httpCode,
                'error' => $error
            ]);
            
            return null;

        } catch (\Exception $e) {
            Log::warning('⚠️ Erro ao obter imagem de URL', [
                'url' => $url,
                'erro' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Converter valor para extenso (pode ser implementado depois)
     */
    public function valorParaExtenso($valor, $moeda = 'MZN')
    {
        // Implementação básica - pode ser expandida depois
        if ($moeda === 'MZN') {
            return number_format($valor, 2, ',', '.') . ' Meticais';
        } elseif ($moeda === 'USD') {
            return number_format($valor, 2, ',', '.') . ' Dólares Americanos';
        } elseif ($moeda === 'ZAR') {
            return number_format($valor, 2, ',', '.') . ' Rands';
        }
        
        return number_format($valor, 2, ',', '.') . ' ' . $moeda;
    }
}