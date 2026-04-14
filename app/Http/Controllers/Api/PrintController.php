<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Viagem;
use App\Models\Motorista;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf;

class PrintController extends Controller
{
    /**
     * Gerar Manifesto da Viagem
     */
    public function generateManifest(Request $request, $id)
    {
        try {
            Log::info('🖨️ Gerando Manifesto com DOMPDF', ['viagem_id' => $id]);

            // 1. Autenticação via token na URL
            $token = $request->query('token');
            if ($token) {
                try {
                    $user = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                    if ($user) Auth::login($user->tokenable);
                } catch (\Exception $e) {
                    Log::warning('Token inválido', ['error' => $e->getMessage()]);
                }
            }

            if (!Auth::check()) {
                return response()->json(['error' => 'Não autenticado'], 401);
            }

            $user = Auth::user();

            // 2. Buscar viagem com segurança (tenant)
            $viagem = $this->buscarViagemComSeguranca($id, $user->tenant_id);
            if (!$viagem) {
                return response()->json(['error' => 'Viagem não encontrada'], 404);
            }

            // 3. Buscar motorista
            $motorista = null;
            if ($viagem->motorista_id) {
                $motorista = Motorista::where('tenant_id', $user->tenant_id)
                    ->where('id', $viagem->motorista_id)
                    ->first();
            }

            // 4. Buscar empresa
            $empresa = null;
            if ($viagem->tenant_id) {
                $empresa = Empresa::where('tenant_id', $viagem->tenant_id)->first();
            }

            // 5. Buscar logo e foto do motorista
            $logoEmpresaBase64 = null;
            if ($empresa && $empresa->logo_url) {
                $logoEmpresaBase64 = $this->getImageBase64($empresa->logo_url);
                if (!$logoEmpresaBase64) {
                    Log::warning('Falha ao converter logo da empresa', ['logo_url' => $empresa->logo_url]);
                }
            }

            $fotoMotoristaBase64 = null;
            if ($motorista && $motorista->foto_url) {
                $fotoMotoristaBase64 = $this->getImageBase64($motorista->foto_url);
            }

            // 6. Preparar dados
            $data = $this->prepareManifestData($viagem, $empresa, $motorista, $logoEmpresaBase64, $fotoMotoristaBase64);

            // 7. Debug - retorna HTML puro
            if ($request->query('debug') === 'true') {
                $html = View::make('pdf.manifest', $data)->render();
                Log::info('Debug HTML - Dados', [
                    'has_logo' => !empty($logoEmpresaBase64),
                    'has_foto' => !empty($fotoMotoristaBase64),
                ]);
                return response($html)->header('Content-Type', 'text/html');
            }

            // 8. Gerar PDF com DOMPDF
            $filename = "MANIFESTO_{$viagem->trip_number}_" . date('Ymd_His') . ".pdf";
            
            $pdf = Pdf::loadView('pdf.manifest', $data);
            
            // Configurações do DOMPDF
            $pdf->setPaper('A4', 'landscape'); // Paisagem para o manifesto
            $pdf->setOptions([
                'defaultFont' => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true, // Para carregar imagens externas
                'dpi' => 150, // Qualidade da imagem
                'isPhpEnabled' => true, // Para funções PHP na view
            ]);

            // Verificar se é download ou visualização
            if ($request->query('download') === '1' || $request->query('download') === 'true') {
                return $pdf->download($filename);
            }

            return $pdf->stream($filename);

        } catch (\Exception $e) {
            Log::error('❌ Erro Crítico no PrintController (generateManifest): ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Erro interno: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Gerar Reforço de Valores (também migrado para DOMPDF)
     */
    public function printExpenses(Request $request, $id)
    {
        try {
            Log::info('🖨️ Gerando Reforço de Valores com DOMPDF', ['viagem_id' => $id]);

            // 1. Autenticação
            $token = $request->query('token');
            if ($token) {
                try {
                    $user = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                    if ($user) Auth::login($user->tokenable);
                } catch (\Exception $e) {
                    Log::warning('Token inválido', ['error' => $e->getMessage()]);
                }
            }

            if (!Auth::check()) {
                return response()->json(['error' => 'Não autenticado'], 401);
            }

            $user = Auth::user();

            // 2. Validar parâmetros
            $despesaIds = $request->query('despesa_ids');
            $tipo = $request->query('tipo', 'original');

            if (!$despesaIds) {
                return response()->json(['error' => 'Nenhuma despesa selecionada'], 422);
            }

            $ids = json_decode($despesaIds, true);
            if (!is_array($ids) || empty($ids)) {
                return response()->json(['error' => 'Formato de despesa_ids inválido'], 422);
            }

            // 3. Buscar viagem e despesas
            $viagem = $this->buscarViagemComSeguranca($id, $user->tenant_id);
            if (!$viagem) {
                return response()->json(['error' => 'Viagem não encontrada'], 404);
            }

            $despesas = \App\Models\Despesa::whereIn('id', $ids)
                ->where('viagem_id', $id)
                ->get();

            if ($despesas->isEmpty()) {
                return response()->json(['error' => 'Despesas não encontradas'], 404);
            }

            // 4. Agrupar por moeda
            $despesasAgrupadas = [];
            foreach ($despesas as $despesa) {
                $moeda = $despesa->moeda ?? 'USD';
                if (!isset($despesasAgrupadas[$moeda])) {
                    $despesasAgrupadas[$moeda] = ['total' => 0, 'quantidade' => 0, 'descricoes' => []];
                }
                $despesasAgrupadas[$moeda]['total'] += $despesa->valor;
                $despesasAgrupadas[$moeda]['quantidade']++;
                if ($despesa->descricao) {
                    $despesasAgrupadas[$moeda]['descricoes'][] = $despesa->descricao;
                }
            }

            // 5. Buscar empresa e logo
            $empresa = null;
            if ($viagem->tenant_id) {
                $empresa = Empresa::where('tenant_id', $viagem->tenant_id)->first();
            }

            $logoEmpresaBase64 = null;
            if ($empresa && $empresa->logo_url) {
                $logoEmpresaBase64 = $this->getImageBase64($empresa->logo_url);
            }

            // 6. Preparar dados
            $data = [
                'tipo' => $tipo,
                'viagem' => $viagem,
                'despesas_agrupadas' => $despesasAgrupadas,
                'empresa' => $empresa,
                'logo_empresa' => $logoEmpresaBase64,
                'usuario' => Auth::check() ? Auth::user()->name : 'Sistema',
                'data_emissao' => date('d/m/Y H:i:s'),
            ];

            // 7. Debug
            if ($request->query('debug') === 'true') {
                $html = View::make('pdf.reforco-valores', $data)->render();
                return response($html)->header('Content-Type', 'text/html');
            }

            // 8. Gerar PDF com DOMPDF
            $filename = "REFORCO_VALORES_{$viagem->trip_number}_" . date('Ymd_His') . ".pdf";
            
            $pdf = Pdf::loadView('pdf.reforco-valores', $data);
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions([
                'defaultFont' => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'isPhpEnabled' => true,
            ]);

            // Adicionar marca d'água se for duplicado
            if ($tipo === 'duplicate') {
                $pdf->output();
                $dompdf = $pdf->getDomPDF();
                $canvas = $dompdf->getCanvas();
                $canvas->page_text(300, 500, "DUPLICADO", null, 60, [0, 0, 0, 0.1]);
            }

            if ($request->query('download') === '1') {
                return $pdf->download($filename);
            }

            return $pdf->stream($filename);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao gerar Reforço de Valores: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Erro interno: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Buscar viagem com segurança (tenant isolation)
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
     * Prepara os dados para o template do manifesto
     */
    private function prepareManifestData($viagem, $empresa, $motorista, $logoEmpresaBase64, $fotoMotoristaBase64)
    {
        $scheduleDate = $viagem->schedule_date
            ? date('d/m/Y', strtotime($viagem->schedule_date))
            : date('d/m/Y');

        $weight = $viagem->weight
            ? number_format($viagem->weight, 2, ',', '.')
            : 'N/A';

        $driverLicense = $motorista ? $motorista->numero_carta : 'N/A';
        $driverPassport = $motorista ? ($motorista->numero_passaporte ?? 'N/A') : 'N/A';
        $driverPhone = $motorista ? ($motorista->telefone ?? 'N/A') : 'N/A';
        $driverNationality = $motorista ? ($motorista->nacionalidade ?? 'N/A') : 'N/A';

        $containerTypes = [
            '20' => "20' DC",
            '40' => "40' DC",
            '45' => "45' HC",
            'BREAK BULK' => 'BREAK BULK',
            'GENERAL CARGO' => 'GENERAL',
            'EMPTY' => 'EMPTY',
        ];

        $containerType = $containerTypes[strtoupper($viagem->cargo_type)] ?? $viagem->cargo_type;

        return [
            'trip_number' => $viagem->trip_number,
            'order_number' => $viagem->order_number ?? 'N/A',
            'bl_number' => $viagem->bl_number ?? 'N/A',
            'schedule_date' => $scheduleDate,
            'from_station' => $viagem->from_station,
            'to_station' => $viagem->to_station,
            'customer_name' => $viagem->customer_name,
            'container_no' => $viagem->container_no ?? 'N/A',
            'container_type' => $containerType,
            'weight' => $weight,
            'commodity' => $viagem->commodity,
            'seal_no' => $viagem->seal_no ?? 'N/A',
            'driver' => $viagem->driver,
            'driver_license' => $driverLicense,
            'driver_passport' => $driverPassport,
            'driver_phone' => $driverPhone,
            'driver_nationality' => $driverNationality,
            'foto_motorista' => $fotoMotoristaBase64,
            'truck_number' => $viagem->truck_number,
            'trailer_number' => $viagem->trailer_number ?? 'N/A',
            'truck_axle' => $viagem->axle_type ?? 'NORMAL',
            'empresa_nome' => $empresa ? $empresa->nome : 'Transport Company',
            'empresa_morada' => $empresa ? $empresa->endereco : null,
            'empresa_telefone' => $empresa ? $empresa->telefone : null,
            'empresa_email' => $empresa ? $empresa->email : null,
            'logo_empresa' => $logoEmpresaBase64,
            'entry_border' => $viagem->entry_border ?? null,
            'current_date' => date('d/m/Y'),
            'current_datetime' => date('d/m/Y H:i:s'),
        ];
    }

    /**
     * Converte imagem do R2 para Base64
     */
    private function getImageBase64($r2Path)
    {
        if (empty($r2Path)) {
            return null;
        }

        try {
            Log::info('Tentando obter imagem', ['path' => $r2Path]);

            // Tentar do R2
            if (Storage::disk('r2')->exists($r2Path)) {
                $imageData = Storage::disk('r2')->get($r2Path);
                if ($imageData) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_buffer($finfo, $imageData);
                    finfo_close($finfo);

                    if (!$mimeType) {
                        $ext = strtolower(pathinfo($r2Path, PATHINFO_EXTENSION));
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

            // Fallback: URL externa
            if (filter_var($r2Path, FILTER_VALIDATE_URL)) {
                $ch = curl_init($r2Path);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 10,
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

            Log::warning('Não foi possível obter imagem', ['path' => $r2Path]);
            return null;

        } catch (\Exception $e) {
            Log::error('Erro ao obter imagem: ' . $e->getMessage());
            return null;
        }
    }
}