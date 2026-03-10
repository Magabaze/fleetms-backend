<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Viagem;
use App\Models\Empresa;
use App\Models\Motorista;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Barryvdh\Snappy\Facades\SnappyPdf;

class PrintController extends Controller
{
    /**
     * Gerar Customs Road Freight Manifest usando Snappy
     */
    public function generateManifest($id)
    {
        try {
            Log::info('🖨️ Gerando PDF Manifesto com Snappy', ['viagem_id' => $id]);

            // 1. Autenticação
            $token = request()->query('token');
            if ($token) {
                try {
                    $user = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                    if ($user) Auth::login($user->tokenable);
                } catch (\Exception $e) {
                    Log::warning('Token inválido', ['error' => $e->getMessage()]);
                }
            }

            // 2. Buscar viagem
            $viagem = null;
            if (Auth::check()) {
                $tenantId = $this->getTenantId();
                $viagem = Viagem::where('tenant_id', $tenantId)->find($id);
            }
            if (!$viagem) $viagem = Viagem::find($id);

            if (!$viagem) {
                return response()->json(['error' => 'Viagem não encontrada'], 404);
            }

            // 3. Buscar empresa
            $empresa = null;
            if ($viagem->tenant_id) {
                $empresa = Empresa::where('tenant_id', $viagem->tenant_id)->first();
                if (!$empresa) {
                    Log::warning('Empresa não encontrada para o tenant', ['tenant_id' => $viagem->tenant_id]);
                } else {
                    Log::info('Empresa encontrada', [
                        'nome' => $empresa->nome, 
                        'logo_url' => $empresa->logo_url,
                        'tenant_id' => $empresa->tenant_id
                    ]);
                }
            }

            // 4. Buscar motorista
            $motorista = null;
            $fotoMotoristaBase64 = null;

            Log::info('Buscando motorista...', ['driver_na_viagem' => $viagem->driver]);

            if ($viagem->driver) {
                $motorista = Motorista::where('nome_completo', $viagem->driver)
                    ->where('tenant_id', $viagem->tenant_id)
                    ->first();

                if ($motorista) {
                    Log::info('Motorista encontrado no DB', [
                        'id' => $motorista->id, 
                        'nome' => $motorista->nome_completo,
                        'foto_url' => $motorista->foto_url,
                        'tenant_id' => $motorista->tenant_id
                    ]);
                    
                    if ($motorista->foto_url) {
                        $fotoMotoristaBase64 = $this->getImageBase64($motorista->foto_url);
                        if (!$fotoMotoristaBase64) {
                            Log::warning('Falha ao converter foto do motorista', ['foto_url' => $motorista->foto_url]);
                        } else {
                            Log::info('Foto do motorista convertida com sucesso', ['base64_length' => strlen($fotoMotoristaBase64)]);
                        }
                    } else {
                        Log::warning('Motorista não tem foto_url no banco');
                    }
                } else {
                    Log::warning('Motorista não encontrado no banco', ['nome_busca' => $viagem->driver]);
                }
            }

            // 5. Buscar logo
            $logoEmpresaBase64 = null;
            if ($empresa && $empresa->logo_url) {
                $logoEmpresaBase64 = $this->getImageBase64($empresa->logo_url);
                if (!$logoEmpresaBase64) {
                    Log::warning('Falha ao converter logo da empresa', ['logo_url' => $empresa->logo_url]);
                } else {
                    Log::info('Logo da empresa convertida com sucesso', ['base64_length' => strlen($logoEmpresaBase64)]);
                }
            }

            // 6. Preparar dados
            $data = $this->prepareManifestData($viagem, $empresa, $motorista, $logoEmpresaBase64, $fotoMotoristaBase64);

            // 7. Renderizar HTML para debug (opcional)
            if (request()->query('debug')) {
                $html = View::make('pdf.manifest', $data)->render();
                
                // Log para debug
                Log::info('Debug HTML - Dados', [
                    'has_logo' => !empty($logoEmpresaBase64),
                    'has_foto' => !empty($fotoMotoristaBase64),
                    'logo_base64_start' => $logoEmpresaBase64 ? substr($logoEmpresaBase64, 0, 50) . '...' : null,
                    'foto_base64_start' => $fotoMotoristaBase64 ? substr($fotoMotoristaBase64, 0, 50) . '...' : null,
                ]);
                
                return response($html)->header('Content-Type', 'text/html');
            }

            // 8. Gerar PDF com Snappy
            $pdf = SnappyPdf::loadView('pdf.manifest', $data)
                ->setOption('enable-local-file-access', true)
                ->setOption('no-background', false)
                ->setOption('page-size', 'A4')
                ->setOption('orientation', 'landscape')
                ->setOption('margin-top', '12')
                ->setOption('margin-right', '14')
                ->setOption('margin-bottom', '10')
                ->setOption('margin-left', '14')
                ->setOption('encoding', 'UTF-8')
                ->setOption('dpi', 150)
                ->setOption('disable-smart-shrinking', false)
                ->setOption('zoom', 1.0)
                ->setOption('enable-external-links', true)
                ->setOption('enable-internal-links', true)
                ->setOption('disable-javascript', true)
                ->setOption('quiet', true);

            if (request()->query('download')) {
                $filename = "MANIFESTO_{$viagem->trip_number}_" . date('Ymd_His') . ".pdf";
                return $pdf->download($filename);
            }

            return $pdf->stream("MANIFESTO_{$viagem->trip_number}.pdf");

        } catch (\Exception $e) {
            Log::error('❌ Erro Crítico no PrintController: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Erro interno: ' . $e->getMessage()], 500);
        }
    }

    private function prepareManifestData($viagem, $empresa, $motorista, $logoEmpresaBase64, $fotoMotoristaBase64)
    {
        $scheduleDate = $viagem->schedule_date ? date('d/m/Y', strtotime($viagem->schedule_date)) : date('d/m/Y');
        $weight = $viagem->weight ? number_format($viagem->weight, 2, ',', '.') : 'N/A';

        $driverLicense = $motorista ? $motorista->numero_carta : 'N/A';
        $driverPassport = $motorista ? ($motorista->numero_passaporte ?? 'N/A') : 'N/A';
        $driverPhone = $motorista ? ($motorista->telefone ?? 'N/A') : 'N/A';
        $driverNationality = $motorista ? ($motorista->nacionalidade ?? 'N/A') : 'N/A';

        $containerTypes = [
            '20' => '20\' DC',
            '40' => '40\' DC', 
            '45' => '45\' HC',
            'BREAK BULK' => 'BREAK BULK',
            'GENERAL CARGO' => 'GENERAL',
            'EMPTY' => 'EMPTY'
        ];
        
        $containerType = $containerTypes[strtoupper($viagem->cargo_type)] ?? $viagem->cargo_type;

        return [
            'trip_number'         => $viagem->trip_number,
            'order_number'        => $viagem->order_number ?? 'N/A',
            'bl_number'           => $viagem->bl_number ?? 'N/A',
            'schedule_date'       => $scheduleDate,
            'from_station'        => $viagem->from_station,
            'to_station'          => $viagem->to_station,
            'customer_name'       => $viagem->customer_name,
            
            'container_no'        => $viagem->container_no ?? 'N/A',
            'container_type'      => $containerType,
            'weight'              => $weight,
            'commodity'           => $viagem->commodity,
            'seal_no'             => $viagem->seal_no ?? 'N/A',
            
            'driver'              => $viagem->driver,
            'driver_license'      => $driverLicense,
            'driver_passport'     => $driverPassport,
            'driver_phone'        => $driverPhone,
            'driver_nationality'  => $driverNationality,
            'foto_motorista'      => $fotoMotoristaBase64, // Base64
            
            'truck_number'        => $viagem->truck_number,
            'trailer_number'      => $viagem->trailer_number ?? 'N/A',
            'truck_axle'          => $viagem->axle_type ?? 'NORMAL',
            
            'empresa_nome'        => $empresa ? $empresa->nome : 'Transport Company',
            'empresa_morada'      => $empresa ? $empresa->endereco : null,
            'empresa_telefone'    => $empresa ? $empresa->telefone : null,
            'empresa_email'       => $empresa ? $empresa->email : null,
            'logo_empresa'        => $logoEmpresaBase64, // Base64
            
            'entry_border'        => $viagem->entry_border ?? null,
            'current_date'        => date('d/m/Y'),
            'current_datetime'    => date('d/m/Y H:i:s'),
        ];
    }

    /**
     * Converte imagem do R2 para Base64
     */
    private function getImageBase64($r2Path)
    {
        if (empty($r2Path)) {
            Log::warning('getImageBase64: caminho vazio');
            return null;
        }

        try {
            Log::info('Tentando obter imagem do R2', ['r2_path' => $r2Path]);

            // Primeiro, tenta obter o conteúdo do R2
            if (Storage::disk('r2')->exists($r2Path)) {
                $imageData = Storage::disk('r2')->get($r2Path);
                
                if ($imageData) {
                    // Detecta o tipo MIME
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_buffer($finfo, $imageData);
                    finfo_close($finfo);
                    
                    // Se não conseguir detectar, usa um padrão
                    if (!$mimeType) {
                        $extension = pathinfo($r2Path, PATHINFO_EXTENSION);
                        $mimeType = match(strtolower($extension)) {
                            'jpg', 'jpeg' => 'image/jpeg',
                            'png' => 'image/png',
                            'gif' => 'image/gif',
                            'webp' => 'image/webp',
                            default => 'image/jpeg'
                        };
                    }
                    
                    $base64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                    
                    Log::info('Imagem convertida para Base64 com sucesso', [
                        'r2_path' => $r2Path,
                        'mime_type' => $mimeType,
                        'base64_length' => strlen($base64)
                    ]);
                    
                    return $base64;
                }
            } else {
                Log::warning('Arquivo não existe no R2', ['r2_path' => $r2Path]);
            }

            // Fallback: Se for uma URL completa
            if (filter_var($r2Path, FILTER_VALIDATE_URL)) {
                Log::info('Tentando baixar via URL', ['url' => $r2Path]);
                
                $ch = curl_init($r2Path);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');

                $imageData = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($imageData !== false && $httpCode === 200) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_buffer($finfo, $imageData);
                    finfo_close($finfo);
                    
                    if (!$mimeType) $mimeType = 'image/jpeg';
                    
                    $base64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                    Log::info('Imagem baixada via URL e convertida', ['url' => $r2Path]);
                    
                    return $base64;
                }
            }

            Log::warning('Não foi possível obter imagem', ['r2_path' => $r2Path]);
            return null;

        } catch (\Exception $e) {
            Log::error('Erro ao obter imagem: ' . $e->getMessage(), [
                'r2_path' => $r2Path,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    private function getTenantId()
    {
        if (Auth::check() && Auth::user()->tenant_id) {
            return Auth::user()->tenant_id;
        }
        return 'default';
    }
}