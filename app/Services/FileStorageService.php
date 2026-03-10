<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class FileStorageService
{
    protected $disk;
    
    public function __construct()
    {
        $this->disk = config('filesystems.default', 'public');
    }
    
    /**
     * Salva um arquivo e retorna o caminho RELATIVO (sem "public/")
     */
    public function salvarArquivo(UploadedFile $arquivo, string $tenantId, string $tipo = 'foto'): ?string
    {
        if (!$arquivo->isValid()) {
            Log::error('❌ Arquivo inválido no upload', [
                'nome' => $arquivo->getClientOriginalName(),
                'erro' => $arquivo->getErrorMessage()
            ]);
            return null;
        }
        
        // Estrutura organizada: motoristas/{tenant}/{ano}/{mes}/{tipo}/
        $ano = date('Y');
        $mes = date('m');
        $nomeUnico = Str::uuid() . '.' . $arquivo->getClientOriginalExtension();
        
        // Caminho RELATIVO dentro do storage (importante!)
        $caminhoRelativo = "motoristas/{$tenantId}/{$ano}/{$mes}/{$tipo}/{$nomeUnico}";
        
        Log::info('💾 Iniciando upload:', [
            'arquivo_original' => $arquivo->getClientOriginalName(),
            'tamanho' => $arquivo->getSize(),
            'mime_type' => $arquivo->getMimeType(),
            'caminho_relativo' => $caminhoRelativo,
            'tenant_id' => $tenantId,
            'tipo' => $tipo
        ]);
        
        try {
            // Salva no disco 'public' - isso retorna caminho relativo
            $caminhoSalvo = Storage::disk('public')->putFileAs(
                dirname($caminhoRelativo),
                $arquivo,
                basename($caminhoRelativo)
            );
            
            Log::info('✅ Arquivo salvo com sucesso:', [
                'caminho_salvo_no_storage' => $caminhoSalvo,
                'caminho_absoluto' => storage_path('app/public/' . $caminhoSalvo),
                'existe_no_disco' => Storage::disk('public')->exists($caminhoSalvo),
                'tamanho_salvo' => Storage::disk('public')->size($caminhoSalvo)
            ]);
            
            return $caminhoSalvo; // Retorna caminho relativo
            
        } catch (\Exception $e) {
            Log::error('❌ ERRO CRÍTICO ao salvar arquivo:', [
                'mensagem' => $e->getMessage(),
                'arquivo' => $arquivo->getClientOriginalName(),
                'caminho' => $caminhoRelativo,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
    
    /**
     * Retorna URL PÚBLICA completa para acessar o arquivo
     */
    public function getUrl(?string $caminhoRelativo): ?string
    {
        if (!$caminhoRelativo) {
            Log::debug('📭 Caminho vazio para gerar URL');
            return null;
        }
        
        // Remove "public/" do início se existir
        $caminhoLimpo = str_replace('public/', '', $caminhoRelativo);
        
        // Garantir que é um caminho relativo dentro do storage público
        if (Storage::disk('public')->exists($caminhoLimpo)) {
            $url = asset('storage/' . $caminhoLimpo);
            
            Log::debug('🔗 URL gerada:', [
                'caminho_original' => $caminhoRelativo,
                'caminho_limpo' => $caminhoLimpo,
                'url_final' => $url,
                'arquivo_existe' => 'SIM'
            ]);
            
            return $url;
        } else {
            Log::warning('⚠️ Arquivo não encontrado no storage:', [
                'caminho' => $caminhoLimpo,
                'disco' => 'public',
                'storage_path' => storage_path('app/public')
            ]);
            return null;
        }
    }
    
    /**
     * Verifica se arquivo existe no storage
     */
    public function arquivoExiste(?string $caminho): bool
    {
        if (!$caminho) return false;
        
        $caminhoLimpo = str_replace('public/', '', $caminho);
        return Storage::disk('public')->exists($caminhoLimpo);
    }
    
    /**
     * Deleta um arquivo do storage
     */
    public function deletarArquivo(?string $caminho): bool
    {
        if (!$caminho) return false;
        
        $caminhoLimpo = str_replace('public/', '', $caminho);
        
        if (Storage::disk('public')->exists($caminhoLimpo)) {
            $deletado = Storage::disk('public')->delete($caminhoLimpo);
            
            Log::info('🗑️ Arquivo deletado:', [
                'caminho' => $caminhoLimpo,
                'sucesso' => $deletado
            ]);
            
            return $deletado;
        }
        
        return false;
    }
}