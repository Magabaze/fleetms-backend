<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Viagem;
use App\Models\Empresa;
use App\Models\Motorista;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PrintController extends Controller
{
    /**
     * Gerar Customs Road Freight Manifest (A4 Landscape)
     */
    public function generateManifest($id)
    {
        try {
            Log::info('📋 Gerando Customs Manifest A4 para viagem', ['viagem_id' => $id]);
            
            // 1. Autenticação via token
            $token = request()->query('token');
            
            if ($token) {
                try {
                    $user = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                    if ($user) {
                        Auth::login($user->tokenable);
                    }
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
            
            if (!$viagem) {
                $viagem = Viagem::find($id);
            }
            
            if (!$viagem) {
                return $this->errorResponse('Viagem não encontrada', 404);
            }
            
            // 3. Buscar empresa
            $empresa = null;
            if ($viagem->tenant_id) {
                $empresa = Empresa::where('tenant_id', $viagem->tenant_id)->first();
            }
            
            // 4. Buscar motorista
            $motorista = null;
            $fotoMotorista = null;
            if ($viagem->driver) {
                $motorista = Motorista::where('nome_completo', $viagem->driver)
                    ->where('tenant_id', $viagem->tenant_id)
                    ->first();
                
                if ($motorista && $motorista->foto) {
                    $fotoMotorista = $this->getFotoMotorista($motorista->foto);
                }
            }
            
            // 5. Gerar Logo da empresa (se existir)
            $logoEmpresa = null;
            if ($empresa && $empresa->logo) {
                $logoEmpresa = $this->getLogoEmpresa($empresa->logo);
            }
            
            // 6. Gerar Customs Manifest A4 Horizontal
            return $this->generateA4LandscapeManifest($viagem, $empresa, $motorista, $logoEmpresa, $fotoMotorista);
            
        } catch (\Exception $e) {
            Log::error('Erro no PrintController: ' . $e->getMessage());
            return $this->errorResponse('Erro interno: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Gerar Logo da empresa (simulado - você precisa adaptar)
     */
    private function getLogoEmpresa($logoPath)
    {
        if (filter_var($logoPath, FILTER_VALIDATE_URL)) {
            return $logoPath;
        }
        
        if (Storage::exists($logoPath)) {
            $mime = Storage::mimeType($logoPath);
            $content = Storage::get($logoPath);
            return 'data:' . $mime . ';base64,' . base64_encode($content);
        }
        
        return $this->generateDefaultLogo();
    }
    
    /**
     * Gerar foto do motorista (simulado)
     */
    private function getFotoMotorista($fotoPath)
    {
        if (filter_var($fotoPath, FILTER_VALIDATE_URL)) {
            return $fotoPath;
        }
        
        if (Storage::exists($fotoPath)) {
            $mime = Storage::mimeType($fotoPath);
            $content = Storage::get($fotoPath);
            return 'data:' . $mime . ';base64,' . base64_encode($content);
        }
        
        return $this->generateDefaultAvatar();
    }
    
    private function generateDefaultLogo()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="40" viewBox="0 0 120 40">
            <rect width="120" height="40" fill="#1a56db" rx="8"/>
            <text x="60" y="25" font-family="Arial" font-size="14" fill="white" text-anchor="middle" font-weight="bold">FLEETMS</text>
        </svg>';
        
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
    
    private function generateDefaultAvatar()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80">
            <circle cx="40" cy="40" r="40" fill="#3b82f6"/>
            <circle cx="40" cy="30" r="15" fill="#ffffff"/>
            <path d="M20,65 Q40,45 60,65" fill="#ffffff"/>
        </svg>';
        
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
    
    /**
     * Gerar Customs Manifest em A4 Horizontal
     */
    private function generateA4LandscapeManifest($viagem, $empresa, $motorista, $logoEmpresa, $fotoMotorista)
    {
        $scheduleDate = $viagem->schedule_date ? date('d/m/Y', strtotime($viagem->schedule_date)) : date('d/m/Y');
        $weight = $viagem->weight ? number_format($viagem->weight, 2) : 'N/A';
        
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
        
        $html = '<!DOCTYPE html>
        <html lang="pt">
        <head>
            <meta charset="UTF-8">
            <title>Customs Manifest - ' . $viagem->trip_number . '</title>
            
            <style>
                /* ===== RESET E CONFIGURAÇÃO A4 ===== */
                @page {
                    size: A4 landscape;
                    margin: 7mm;
                }
                
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: "Arial", sans-serif;
                    font-size: 8px;
                    line-height: 1.1;
                    color: #000000;
                    background: #ffffff;
                    width: 297mm;
                    height: 183mm; /* 210mm - margens */
                    margin: 0 auto;
                    padding: 0;
                    position: relative;
                }
                
                /* ===== CONTAINER PRINCIPAL ===== */
                .container {
                    width: 100%;
                    height: 100%;
                    padding: 7mm;
                    position: relative;
                }
                
                /* ===== CABEÇALHO ===== */
                .header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    margin-bottom: 5mm;
                    padding-bottom: 3mm;
                    border-bottom: 2px solid #000;
                    height: 22mm;
                }
                
                .logo-box {
                    width: 45mm;
                }
                
                .logo {
                    max-width: 40mm;
                    max-height: 15mm;
                }
                
                .title-box {
                    text-align: center;
                    flex: 1;
                    padding: 0 5mm;
                }
                
                .main-title {
                    font-size: 12px;
                    font-weight: bold;
                    text-transform: uppercase;
                    margin-bottom: 1mm;
                }
                
                .sub-title {
                    font-size: 8px;
                    color: #555;
                }
                
                .doc-info {
                    width: 45mm;
                    text-align: right;
                }
                
                .doc-number {
                    font-size: 9px;
                    font-weight: bold;
                    color: #1a56db;
                }
                
                .doc-date {
                    font-size: 8px;
                    color: #666;
                }
                
                /* ===== CONTEÚDO PRINCIPAL - 2 COLUNAS ===== */
                .main-content {
                    display: flex;
                    gap: 6mm;
                    margin-bottom: 4mm;
                    height: 95mm;
                }
                
                .left-column {
                    flex: 1.1;
                    display: flex;
                    flex-direction: column;
                    gap: 3mm;
                }
                
                .right-column {
                    flex: 0.9;
                    display: flex;
                    flex-direction: column;
                    gap: 3mm;
                }
                
                /* ===== SEÇÕES COMUNS ===== */
                .section {
                    border: 1px solid #000;
                    border-radius: 2px;
                    overflow: hidden;
                }
                
                .section-title {
                    background: #333;
                    color: white;
                    padding: 2px 4px;
                    font-size: 8px;
                    font-weight: bold;
                    text-align: center;
                }
                
                .section-content {
                    padding: 3mm;
                }
                
                /* ===== TABELAS ===== */
                .info-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 7.5px;
                }
                
                .info-table td {
                    border: 1px solid #ccc;
                    padding: 2px 3px;
                    vertical-align: middle;
                }
                
                .info-label {
                    background: #f5f5f5;
                    font-weight: bold;
                    width: 40%;
                }
                
                .cargo-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 7px;
                    margin-top: 2mm;
                }
                
                .cargo-table th {
                    background: #555;
                    color: white;
                    padding: 2px 3px;
                    border: 1px solid #000;
                    font-weight: bold;
                    text-align: center;
                }
                
                .cargo-table td {
                    border: 1px solid #000;
                    padding: 2px 3px;
                    text-align: center;
                }
                
                /* ===== MOTORISTA ===== */
                .driver-section {
                    display: flex;
                    gap: 3mm;
                    align-items: flex-start;
                }
                
                .driver-photo {
                    width: 25mm;
                    text-align: center;
                }
                
                .photo-frame {
                    width: 23mm;
                    height: 27mm;
                    border: 1px solid #000;
                    margin-bottom: 1mm;
                    overflow: hidden;
                    background: #f9f9f9;
                }
                
                .driver-photo-img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }
                
                .driver-name {
                    font-size: 8px;
                    font-weight: bold;
                    text-align: center;
                }
                
                .driver-info {
                    flex: 1;
                }
                
                /* ===== VEÍCULO E FRONTEIRA ===== */
                .vehicle-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 2mm;
                    margin-top: 2mm;
                }
                
                .vehicle-card {
                    border: 1px solid #ccc;
                    padding: 2mm;
                    text-align: center;
                    border-radius: 2px;
                }
                
                .vehicle-label {
                    font-size: 7px;
                    color: #666;
                    margin-bottom: 1px;
                }
                
                .vehicle-value {
                    font-size: 8px;
                    font-weight: bold;
                }
                
                .border-grid {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 2mm;
                    margin-top: 2mm;
                }
                
                /* ===== DECLARAÇÃO ===== */
                .declaration {
                    padding: 3mm;
                    border: 1px solid #000;
                    font-size: 7.5px;
                    line-height: 1.2;
                    margin-bottom: 3mm;
                }
                
                /* ===== CUSTOMS E ASSINATURAS ===== */
                .signatures-customs {
                    display: grid;
                    grid-template-columns: 2fr 1fr;
                    gap: 4mm;
                    margin-bottom: 3mm;
                }
                
                .signatures {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 4mm;
                }
                
                .signature-box {
                    text-align: center;
                }
                
                .signature-line {
                    height: 12mm;
                    border-bottom: 1px solid #000;
                    margin-bottom: 1mm;
                }
                
                .signature-label {
                    font-size: 7.5px;
                    font-weight: bold;
                    margin-bottom: 1mm;
                }
                
                .signature-name {
                    font-size: 7.5px;
                    color: #555;
                }
                
                .customs-box {
                    text-align: center;
                }
                
                .customs-stamp {
                    width: 100%;
                    height: 20mm;
                    border: 1px solid #000;
                    margin-bottom: 1mm;
                    position: relative;
                }
                
                .stamp-label {
                    position: absolute;
                    bottom: 1mm;
                    left: 0;
                    right: 0;
                    font-size: 7px;
                    text-align: center;
                }
                
                /* ===== RODAPÉ ===== */
                .footer {
                    position: absolute;
                    bottom: 7mm;
                    left: 7mm;
                    right: 7mm;
                    text-align: center;
                    font-size: 6.5px;
                    color: #666;
                    border-top: 1px solid #ccc;
                    padding-top: 1mm;
                }
                
                /* ===== CONTROLES DE IMPRESSÃO ===== */
                .print-controls {
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    z-index: 1000;
                    background: #1a56db;
                    padding: 8px 12px;
                    border-radius: 4px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                }
                
                .print-btn {
                    background: none;
                    border: none;
                    color: white;
                    font-size: 9px;
                    font-weight: bold;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    gap: 5px;
                }
                
                /* ===== UTILITÁRIOS ===== */
                .bold { font-weight: bold; }
                .text-center { text-align: center; }
                .mb-1 { margin-bottom: 1mm; }
                .mt-1 { margin-top: 1mm; }
                
                /* ===== IMPRESSÃO ===== */
                @media print {
                    body {
                        width: 297mm !important;
                        height: 183mm !important;
                        margin: 0 !important;
                        padding: 0 !important;
                    }
                    
                    .print-controls {
                        display: none !important;
                    }
                    
                    .container {
                        padding: 7mm !important;
                    }
                }
            </style>
        </head>
        <body>
            
            <!-- Controles de Impressão -->
            <div class="print-controls">
                <button class="print-btn" onclick="window.print()">
                    🖨️ IMPRIMIR
                </button>
            </div>
            
            <!-- Container Principal -->
            <div class="container">
                
                <!-- Cabeçalho -->
                <div class="header">
                    <div class="logo-box">
                        <img src="' . $logoEmpresa . '" alt="Logo" class="logo">
                    </div>
                    
                    <div class="title-box">
                        <div class="main-title">CUSTOMS ROAD FREIGHT MANIFEST</div>
                        <div class="sub-title">Documento Oficial de Transporte Rodoviário Internacional</div>
                    </div>
                    
                    <div class="doc-info">
                        <div class="doc-number">REF: ' . $viagem->trip_number . '</div>
                        <div class="doc-date">Date: ' . date('d/m/Y') . '</div>
                    </div>
                </div>
                
                <!-- Conteúdo Principal -->
                <div class="main-content">
                    
                    <!-- Coluna Esquerda -->
                    <div class="left-column">
                        
                        <!-- Informações da Viagem -->
                        <div class="section">
                            <div class="section-title">TRIP INFORMATION</div>
                            <div class="section-content">
                                <table class="info-table">
                                    <tr>
                                        <td class="info-label">TRIP REFERENCE</td>
                                        <td class="bold">' . $viagem->trip_number . '</td>
                                    </tr>
                                    <tr>
                                        <td class="info-label">ORDER NUMBER</td>
                                        <td>' . ($viagem->order_number ?? 'N/A') . '</td>
                                    </tr>
                                    <tr>
                                        <td class="info-label">BL/AWB NUMBER</td>
                                        <td>' . ($viagem->bl_number ?? 'N/A') . '</td>
                                    </tr>
                                    <tr>
                                        <td class="info-label">SCHEDULE DATE</td>
                                        <td>' . $scheduleDate . '</td>
                                    </tr>
                                    <tr>
                                        <td class="info-label">LOADING DEPOT</td>
                                        <td class="bold">' . $viagem->from_station . '</td>
                                    </tr>
                                    <tr>
                                        <td class="info-label">OFFLOADING DEPOT</td>
                                        <td class="bold">' . $viagem->to_station . '</td>
                                    </tr>
                                    <tr>
                                        <td class="info-label">CUSTOMER</td>
                                        <td>' . $viagem->customer_name . '</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Detalhes da Carga -->
                        <div class="section">
                            <div class="section-title">CARGO DETAILS</div>
                            <div class="section-content">
                                <table class="cargo-table">
                                    <thead>
                                        <tr>
                                            <th>NO.</th>
                                            <th>CONTAINER NO.</th>
                                            <th>TYPE</th>
                                            <th>SEAL NO.</th>
                                            <th>WEIGHT (kg)</th>
                                            <th>COMMODITY</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>1</td>
                                            <td>' . ($viagem->container_no ?? 'N/A') . '</td>
                                            <td>' . $containerType . '</td>
                                            <td>N/A</td>
                                            <td>' . $weight . '</td>
                                            <td>' . $viagem->commodity . '</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Declaração -->
                        <div class="declaration">
                            <strong>DECLARAÇÃO:</strong> I hereby certify that the particulars shown on this manifest are true reflection of all authorised goods carried on the above mentioned vehicle. ' . ($empresa ? $empresa->nome : 'Transport Company') . ' shall bear no responsibility for any cargo not declared on the road freight manifest.
                        </div>
                        
                    </div>
                    
                    <!-- Coluna Direita -->
                    <div class="right-column">
                        
                        <!-- Informações do Motorista -->
                        <div class="section">
                            <div class="section-title">DRIVER INFORMATION</div>
                            <div class="section-content">
                                <div class="driver-section">
                                    <div class="driver-photo">
                                        <div class="photo-frame">
                                            <img src="' . $fotoMotorista . '" alt="Driver Photo" class="driver-photo-img">
                                        </div>
                                        <div class="driver-name">' . $viagem->driver . '</div>
                                    </div>
                                    
                                    <div class="driver-info">
                                        <table class="info-table">
                                            <tr>
                                                <td class="info-label">DRIVER NAME</td>
                                                <td class="bold">' . $viagem->driver . '</td>
                                            </tr>
                                            <tr>
                                                <td class="info-label">DRIVER LICENSE</td>
                                                <td>' . $driverLicense . '</td>
                                            </tr>
                                            <tr>
                                                <td class="info-label">PASSPORT NO.</td>
                                                <td>' . $driverPassport . '</td>
                                            </tr>
                                            <tr>
                                                <td class="info-label">PHONE NUMBER</td>
                                                <td>' . $driverPhone . '</td>
                                            </tr>
                                            <tr>
                                                <td class="info-label">NATIONALITY</td>
                                                <td>' . $driverNationality . '</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Informações do Veículo -->
                        <div class="section">
                            <div class="section-title">VEHICLE INFORMATION</div>
                            <div class="section-content">
                                <div class="vehicle-grid">
                                    <div class="vehicle-card">
                                        <div class="vehicle-label">TRUCK</div>
                                        <div class="vehicle-value">' . $viagem->truck_number . '</div>
                                    </div>
                                    <div class="vehicle-card">
                                        <div class="vehicle-label">TRAILER</div>
                                        <div class="vehicle-value">' . ($viagem->trailer_number ?? 'N/A') . '</div>
                                    </div>
                                    <div class="vehicle-card">
                                        <div class="vehicle-label">BODY TYPE</div>
                                        <div class="vehicle-value">' . $containerType . '</div>
                                    </div>
                                    <div class="vehicle-card">
                                        <div class="vehicle-label">AXLE TYPE</div>
                                        <div class="vehicle-value">NORMAL</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Informações de Fronteira -->
                        <div class="section">
                            <div class="section-title">BORDER INFORMATION</div>
                            <div class="section-content">
                                <div class="border-grid">
                                    <div class="vehicle-card">
                                        <div class="vehicle-label">ENTRY BORDER</div>
                                        <div class="vehicle-value">MACHIPANDA</div>
                                    </div>
                                    <div class="vehicle-card">
                                        <div class="vehicle-label">DROP OFF</div>
                                        <div class="vehicle-value">' . $viagem->to_station . '</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                    
                </div>
                
                <!-- Assinaturas e Carimbos -->
                <div class="signatures-customs">
                    <div class="signatures">
                        <div class="signature-box">
                            <div class="signature-label">DRIVER SIGNATURE</div>
                            <div class="signature-line"></div>
                            <div class="signature-name">' . $viagem->driver . '</div>
                        </div>
                        
                        <div class="signature-box">
                            <div class="signature-label">TRANSPORTER SIGNATURE & STAMP</div>
                            <div class="signature-line"></div>
                            <div class="signature-name">' . ($empresa ? $empresa->nome : 'Transport Company') . '</div>
                        </div>
                    </div>
                    
                    <div class="customs-box">
                        <div class="signature-label">FOR CUSTOMS USE ONLY</div>
                        <div class="customs-stamp">
                            <div class="stamp-label">Customs Stamp Exit</div>
                        </div>
                        <div style="font-size: 7px;">Report No.: ________________</div>
                    </div>
                </div>
                
                <!-- Rodapé -->
                <div class="footer">
                    <div class="bold mb-1">' . ($empresa ? $empresa->nome : 'FleetMS Transport Management System') . '</div>
                    <div>';
        
        if ($empresa) {
            if ($empresa->morada) {
                $html .= $empresa->morada . ' | ';
            }
            if ($empresa->telefone) {
                $html .= 'Tel: ' . $empresa->telefone . ' | ';
            }
            if ($empresa->email) {
                $html .= 'Email: ' . $empresa->email . ' | ';
            }
        }
        
        $html .= 'Generated by FleetMS | ' . date('d/m/Y H:i:s') . '
                    </div>
                </div>
                
            </div>
            
            <script>
                // Atalho para impressão
                document.addEventListener("keydown", function(e) {
                    if ((e.ctrlKey || e.metaKey) && e.key === "p") {
                        e.preventDefault();
                        window.print();
                    }
                });
                
                // Garantir que tudo caiba em 1 página
                window.onload = function() {
                    console.log("Documento A4 organizado carregado");
                    document.body.style.height = "183mm";
                };
            </script>
            
        </body>
        </html>';
        
        return response($html)->header('Content-Type', 'text/html');
    }
    
    private function errorResponse($message, $statusCode)
    {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <title>Error</title>
            <style>
                body { font-family: Arial; padding: 40px; text-align: center; }
                .error { color: red; margin: 20px 0; }
                button { background: #1a56db; color: white; padding: 10px 20px; border: none; cursor: pointer; }
            </style>
        </head>
        <body>
            <h1>Error ' . $statusCode . '</h1>
            <div class="error">' . htmlspecialchars($message) . '</div>
            <button onclick="window.history.back()">Go Back</button>
        </body>
        </html>';
        
        return response($html, $statusCode);
    }
    
    private function getTenantId()
    {
        if (Auth::check() && Auth::user()->tenant_id) {
            return Auth::user()->tenant_id;
        }
        
        return 'default';
    }
}