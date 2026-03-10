<?php
// app/Exports/BaseExport.php

namespace App\Exports;

use App\Models\Empresa;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

abstract class BaseExport
{
    protected $empresaNome = 'FLEETMS';
    protected $logoData = null;
    protected $logoMime = null;
    protected $empresa = null;
    protected $tenantId = null;
    
    abstract protected function totalColunas(): int;
    
    protected function carregarEmpresaPorTenant()
    {
        try {
            if (!Auth::check()) {
                Log::warning('BaseExport: Usuário não autenticado');
                return;
            }

            $this->tenantId = Auth::user()->tenant_id;
            
            if (!$this->tenantId) {
                Log::warning('BaseExport: Tenant ID não encontrado');
                return;
            }

            $this->empresa = Empresa::where('tenant_id', $this->tenantId)->first();
            
            if (!$this->empresa) {
                Log::warning('BaseExport: Empresa não encontrada');
                return;
            }

            if ($this->empresa->nome) {
                $this->empresaNome = strtoupper($this->empresa->nome);
            }
            
            // Carregar logo se existir
            if ($this->empresa->logo_url) {
                $this->carregarLogo($this->empresa->logo_url);
            }

        } catch (\Exception $e) {
            Log::error('BaseExport: Erro ao buscar empresa', [
                'message' => $e->getMessage()
            ]);
        }
    }
    
    protected function carregarLogo($logoUrl)
    {
        try {
            // Tentar via Storage R2
            if (Storage::disk('r2')->exists($logoUrl)) {
                $this->logoData = Storage::disk('r2')->get($logoUrl);
                $this->logoMime = Storage::disk('r2')->mimeType($logoUrl);
                return;
            }
            
            // Fallback para URL pública
            $publicDomain = rtrim(env('R2_PUBLIC_DOMAIN', ''), '/');
            if ($publicDomain) {
                $urlPublica = $publicDomain . '/' . ltrim($logoUrl, '/');
                
                $ch = curl_init($urlPublica);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                
                $this->logoData = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200 && $this->logoData) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $this->logoMime = finfo_buffer($finfo, $this->logoData);
                    finfo_close($finfo);
                }
            }
            
        } catch (\Exception $e) {
            Log::error('BaseExport: Erro ao carregar logo', [
                'message' => $e->getMessage()
            ]);
        }
    }
}