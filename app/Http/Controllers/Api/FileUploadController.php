<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    public function __construct()
    {
        // ✅ Garante que só usuários logados possam fazer upload
        $this->middleware('auth:sanctum');
    }

    /**
     * Obtém o ID do Tenant do usuário autenticado
     */
    private function getTenantId()
    {
        return Auth::user()->tenant_id ?? 'default';
    }

    /**
     * Upload do Logo da Empresa
     * Rota: POST /api/upload/logo
     */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120' // 5MB
        ]);

        try {
            $tenantId = $this->getTenantId();
            $file = $request->file('logo');
            
            // Caminho no R2: logos/tenant_id/logo_timestamp.ext
            $path = Storage::disk('r2')->putFile(
                "logos/{$tenantId}", 
                $file, 
                ['ContentType' => $file->getMimeType()]
            );

            $url = Storage::disk('r2')->url($path);

            Log::info('Logo enviado', ['tenant_id' => $tenantId, 'path' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Logo enviado com sucesso',
                'path' => $path,
                'url' => $url
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao fazer upload de logo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao enviar logo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload de Foto do Motorista
     * Rota: POST /api/upload/motorista-foto
     */
    public function uploadMotoristaFoto(Request $request)
    {
        $request->validate([
            'foto' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // 10MB
            'motorista_id' => 'required|integer'
        ]);

        try {
            $tenantId = $this->getTenantId();
            $motoristaId = $request->input('motorista_id');
            $file = $request->file('foto');
            
            // Caminho no R2: motoristas/tenant_id/motorista_id/fotos/timestamp.ext
            $path = Storage::disk('r2')->putFile(
                "motoristas/{$tenantId}/{$motoristaId}/fotos", 
                $file,
                ['ContentType' => $file->getMimeType()]
            );

            $url = Storage::disk('r2')->url($path);

            Log::info('Foto de motorista enviada', [
                'tenant_id' => $tenantId, 
                'motorista_id' => $motoristaId, 
                'path' => $path
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Foto do motorista enviada',
                'path' => $path,
                'url' => $url
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao fazer upload de foto motorista: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao enviar foto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload de Documentos (CNH, Passaporte, etc)
     * Rota: POST /api/upload/documento
     */
    public function uploadDocumento(Request $request)
    {
        $request->validate([
            'documento' => 'required|mimes:pdf,jpeg,png,jpg|max:10240', // 10MB
            'tipo' => 'required|string|max:50', // ex: 'CNH', 'Passaporte', 'Comprovante'
            'motorista_id' => 'required|integer'
        ]);

        try {
            $tenantId = $this->getTenantId();
            $tipo = Str::slug($request->input('tipo'), '_'); // Sanitiza o nome da pasta
            $motoristaId = $request->input('motorista_id');
            $file = $request->file('documento');

            // Caminho no R2: motoristas/tenant_id/motorista_id/documentos/tipo/timestamp.ext
            $path = Storage::disk('r2')->putFile(
                "motoristas/{$tenantId}/{$motoristaId}/documentos/{$tipo}", 
                $file,
                ['ContentType' => $file->getMimeType()]
            );

            $url = Storage::disk('r2')->url($path);

            Log::info('Documento enviado', [
                'tenant_id' => $tenantId, 
                'motorista_id' => $motoristaId,
                'tipo' => $tipo,
                'path' => $path
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Documento enviado com sucesso',
                'path' => $path,
                'url' => $url
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao fazer upload de documento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao enviar documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deletar arquivo do R2
     * Rota: DELETE /api/upload/arquivo
     */
    public function deleteFile(Request $request)
    {
        $request->validate([
            'path' => 'required|string'
        ]);

        try {
            $tenantId = $this->getTenantId();
            $path = $request->input('path');

            // ✅ SEGURANÇA: Verifica se o caminho pertence ao tenant atual antes de deletar
            // Isso impede que um usuário delete arquivos de outro tenant
            if (!str_starts_with($path, "motoristas/{$tenantId}") && 
                !str_starts_with($path, "logos/{$tenantId}")) {
                
                Log::warning('Tentativa de deletar arquivo de outro tenant', [
                    'tenant_id' => $tenantId,
                    'path' => $path
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para deletar este arquivo.'
                ], 403);
            }

            Storage::disk('r2')->delete($path);

            Log::info('Arquivo deletado', ['path' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Arquivo deletado com sucesso'
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao deletar arquivo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao deletar: ' . $e->getMessage()
            ], 500);
        }
    }
}