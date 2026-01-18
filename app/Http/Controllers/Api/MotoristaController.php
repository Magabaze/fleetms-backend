<?php
// app/Http/Controllers/Api/MotoristaController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Motorista;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MotoristaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function getTenantId()
    {
        $user = Auth::user();
        return $user->tenant_id ?? 'default';
    }

    // Validar se é imagem
    private function isImage($file)
    {
        $mime = $file->getMimeType();
        return str_starts_with($mime, 'image/');
    }

    // Converter para camelCase
    private function paraCamelCase($motorista)
    {
        return [
            'id' => $motorista->id,
            'nomeCompleto' => $motorista->nome_completo,
            'numeroCarta' => $motorista->numero_carta,
            'numeroPassaporte' => $motorista->numero_passaporte,
            'nacionalidade' => $motorista->nacionalidade,
            'telefone' => $motorista->telefone,
            'telefoneAlternativo' => $motorista->telefone_alternativo,
            'email' => $motorista->email,
            'endereco' => $motorista->endereco,
            'tipoLicenca' => $motorista->tipo_licenca,
            'validadeLicenca' => $motorista->validade_licenca,
            'validadePassaporte' => $motorista->validade_passaporte,
            'status' => $motorista->status,
            'observacoes' => $motorista->observacoes,
            'fotoUrl' => $motorista->foto_url ? Storage::url($motorista->foto_url) : null,
            'fotoCartaUrl' => $motorista->foto_carta_url ? Storage::url($motorista->foto_carta_url) : null,
            'fotoPassaporteUrl' => $motorista->foto_passaporte_url ? Storage::url($motorista->foto_passaporte_url) : null,
            'documentos' => $motorista->documentos,
            'criadoPor' => $motorista->criado_por,
            'createdAt' => $motorista->created_at->toISOString(),
            'updatedAt' => $motorista->updated_at->toISOString()
        ];
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId();
        
        Log::info('📥 GET /api/motoristas', [
            'user_id' => Auth::id(),
            'tenant_id' => $tenantId,
            'query' => $request->all()
        ]);
        
        try {
            $query = Motorista::where('tenant_id', $tenantId);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nome_completo', 'like', "%{$search}%")
                      ->orWhere('numero_carta', 'like', "%{$search}%")
                      ->orWhere('numero_passaporte', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('telefone', 'like', "%{$search}%");
                });
            }
            
            if ($request->has('status') && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $motoristas = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            $motoristasCamelCase = $motoristas->map(function ($motorista) {
                return $this->paraCamelCase($motorista);
            });
            
            Log::info('✅ Motoristas listados', [
                'total' => $motoristas->total(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $motoristasCamelCase->toArray(),
                'pagination' => [
                    'page' => $motoristas->currentPage(),
                    'limit' => $perPage,
                    'total' => $motoristas->total(),
                    'totalPages' => $motoristas->lastPage(),
                    'hasNextPage' => $motoristas->hasMorePages(),
                    'hasPrevPage' => $motoristas->currentPage() > 1,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar motoristas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $tenantId = $this->getTenantId();
        
        Log::info('📥 POST /api/motoristas', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->except(['foto', 'fotoCarta', 'fotoPassaporte'])
        ]);
        
        $validator = Validator::make($request->all(), [
            'nomeCompleto' => 'required|string|max:255',
            'numeroCarta' => 'required|string|max:50|unique:motoristas,numero_carta,NULL,id,tenant_id,' . $tenantId,
            'numeroPassaporte' => 'nullable|string|max:50',
            'nacionalidade' => 'required|string|max:50',
            'telefone' => 'required|string|max:20',
            'telefoneAlternativo' => 'nullable|string|max:20',
            'tipoLicenca' => 'required|in:A,B,C,D,E',
            'validadeLicenca' => 'required|date',
            'validadePassaporte' => 'nullable|date',
            'status' => 'required|in:Ativo,Inativo,Férias,Licença',
            'email' => 'nullable|email|unique:motoristas,email,NULL,id,tenant_id,' . $tenantId,
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'fotoCarta' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,pdf|max:5120',
            'fotoPassaporte' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,pdf|max:5120',
        ], [
            'numeroCarta.unique' => 'Já existe um motorista com esta carta de condução',
            'email.unique' => 'Já existe um motorista com este email',
            'foto.image' => 'A foto deve ser uma imagem válida',
            '*.mimes' => 'O arquivo deve ser: JPEG, PNG, JPG, GIF, WebP ou PDF',
            '*.max' => 'O arquivo não deve exceder 5MB'
        ]);
        
        if ($validator->fails()) {
            Log::error('❌ Validação falhou', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $dados = [
                'nome_completo' => $request->nomeCompleto,
                'numero_carta' => $request->numeroCarta,
                'numero_passaporte' => $request->numeroPassaporte,
                'nacionalidade' => $request->nacionalidade,
                'telefone' => $request->telefone,
                'telefone_alternativo' => $request->telefoneAlternativo,
                'tipo_licenca' => $request->tipoLicenca,
                'validade_licenca' => $request->validadeLicenca,
                'validade_passaporte' => $request->validadePassaporte,
                'status' => $request->status,
                'email' => $request->email ?? '',
                'endereco' => $request->endereco ?? '',
                'observacoes' => $request->observacoes ?? '',
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ];
            
            // Processar foto do motorista
            if ($request->hasFile('foto') && $request->file('foto')->isValid()) {
                $file = $request->file('foto');
                $fileName = 'foto_' . time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $path = "motoristas/{$tenantId}/fotos";
                
                $file->storeAs($path, $fileName, 'public');
                $dados['foto_url'] = "{$path}/{$fileName}";
                
                Log::info('📷 Foto salva', [
                    'nome_arquivo' => $fileName,
                    'caminho' => $path,
                    'tenant_id' => $tenantId
                ]);
            }
            
            // Processar foto da carta
            if ($request->hasFile('fotoCarta') && $request->file('fotoCarta')->isValid()) {
                $file = $request->file('fotoCarta');
                $fileName = 'carta_' . time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $path = "motoristas/{$tenantId}/documentos";
                
                $file->storeAs($path, $fileName, 'public');
                $dados['foto_carta_url'] = "{$path}/{$fileName}";
                
                Log::info('📄 Carta salva', [
                    'nome_arquivo' => $fileName,
                    'caminho' => $path,
                    'tipo' => $file->getMimeType(),
                    'tenant_id' => $tenantId
                ]);
            }
            
            // Processar foto do passaporte
            if ($request->hasFile('fotoPassaporte') && $request->file('fotoPassaporte')->isValid()) {
                $file = $request->file('fotoPassaporte');
                $fileName = 'passaporte_' . time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $path = "motoristas/{$tenantId}/documentos";
                
                $file->storeAs($path, $fileName, 'public');
                $dados['foto_passaporte_url'] = "{$path}/{$fileName}";
                
                Log::info('📄 Passaporte salvo', [
                    'nome_arquivo' => $fileName,
                    'caminho' => $path,
                    'tipo' => $file->getMimeType(),
                    'tenant_id' => $tenantId
                ]);
            }
            
            Log::info('💾 Salvando motorista', $dados);
            
            $motorista = Motorista::create($dados);
            
            Log::info('✅ Motorista criado', [
                'id' => $motorista->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($motorista),
                'message' => 'Motorista criado com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar motorista: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $tenantId = $this->getTenantId();
        
        try {
            $motorista = Motorista::where('tenant_id', $tenantId)->find($id);
            
            if (!$motorista) {
                return response()->json([
                    'success' => false,
                    'error' => 'Motorista não encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($motorista)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar motorista: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId();
        
        Log::info('📥 PUT /api/motoristas/' . $id, [
            'user_id' => Auth::id(),
            'tenant_id' => $tenantId,
            'dados' => $request->except(['foto', 'fotoCarta', 'fotoPassaporte'])
        ]);
        
        try {
            $motorista = Motorista::where('tenant_id', $tenantId)->find($id);
            
            if (!$motorista) {
                return response()->json([
                    'success' => false,
                    'error' => 'Motorista não encontrado'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'nomeCompleto' => 'required|string|max:255',
                'numeroCarta' => 'required|string|max:50|unique:motoristas,numero_carta,' . $id . ',id,tenant_id,' . $tenantId,
                'numeroPassaporte' => 'nullable|string|max:50',
                'nacionalidade' => 'required|string|max:50',
                'telefone' => 'required|string|max:20',
                'telefoneAlternativo' => 'nullable|string|max:20',
                'tipoLicenca' => 'required|in:A,B,C,D,E',
                'validadeLicenca' => 'required|date',
                'validadePassaporte' => 'nullable|date',
                'status' => 'required|in:Ativo,Inativo,Férias,Licença',
                'email' => 'nullable|email|unique:motoristas,email,' . $id . ',id,tenant_id,' . $tenantId,
                'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
                'fotoCarta' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,pdf|max:5120',
                'fotoPassaporte' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,pdf|max:5120',
                'removerFoto' => 'nullable|boolean',
            ], [
                'numeroCarta.unique' => 'Já existe um motorista com esta carta de condução',
                'email.unique' => 'Já existe um motorista com este email'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $dadosAtualizacao = [
                'nome_completo' => $request->nomeCompleto,
                'numero_carta' => $request->numeroCarta,
                'numero_passaporte' => $request->numeroPassaporte,
                'nacionalidade' => $request->nacionalidade,
                'telefone' => $request->telefone,
                'telefone_alternativo' => $request->telefoneAlternativo,
                'tipo_licenca' => $request->tipoLicenca,
                'validade_licenca' => $request->validadeLicenca,
                'validade_passaporte' => $request->validadePassaporte,
                'status' => $request->status,
                'email' => $request->email ?? $motorista->email,
                'endereco' => $request->endereco ?? $motorista->endereco,
                'observacoes' => $request->observacoes ?? $motorista->observacoes,
            ];
            
            // Processar foto do motorista
            if ($request->hasFile('foto') && $request->file('foto')->isValid()) {
                // Remover foto antiga se existir
                if ($motorista->foto_url && Storage::disk('public')->exists($motorista->foto_url)) {
                    Storage::disk('public')->delete($motorista->foto_url);
                }
                
                $file = $request->file('foto');
                $fileName = 'foto_' . time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $path = "motoristas/{$tenantId}/fotos";
                
                $file->storeAs($path, $fileName, 'public');
                $dadosAtualizacao['foto_url'] = "{$path}/{$fileName}";
                
                Log::info('📷 Foto atualizada', [
                    'nome_arquivo' => $fileName,
                    'caminho' => $path,
                    'tenant_id' => $tenantId
                ]);
            } elseif ($request->has('removerFoto') && $request->removerFoto) {
                // Remover foto se solicitado
                if ($motorista->foto_url && Storage::disk('public')->exists($motorista->foto_url)) {
                    Storage::disk('public')->delete($motorista->foto_url);
                }
                $dadosAtualizacao['foto_url'] = null;
            }
            
            // Processar foto da carta
            if ($request->hasFile('fotoCarta') && $request->file('fotoCarta')->isValid()) {
                // Remover documento antigo se existir
                if ($motorista->foto_carta_url && Storage::disk('public')->exists($motorista->foto_carta_url)) {
                    Storage::disk('public')->delete($motorista->foto_carta_url);
                }
                
                $file = $request->file('fotoCarta');
                $fileName = 'carta_' . time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $path = "motoristas/{$tenantId}/documentos";
                
                $file->storeAs($path, $fileName, 'public');
                $dadosAtualizacao['foto_carta_url'] = "{$path}/{$fileName}";
            }
            
            // Processar foto do passaporte
            if ($request->hasFile('fotoPassaporte') && $request->file('fotoPassaporte')->isValid()) {
                // Remover documento antigo se existir
                if ($motorista->foto_passaporte_url && Storage::disk('public')->exists($motorista->foto_passaporte_url)) {
                    Storage::disk('public')->delete($motorista->foto_passaporte_url);
                }
                
                $file = $request->file('fotoPassaporte');
                $fileName = 'passaporte_' . time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $path = "motoristas/{$tenantId}/documentos";
                
                $file->storeAs($path, $fileName, 'public');
                $dadosAtualizacao['foto_passaporte_url'] = "{$path}/{$fileName}";
            }
            
            $motorista->update($dadosAtualizacao);
            
            Log::info('✅ Motorista atualizado', [
                'id' => $id,
                'tenant_id' => $tenantId
            ]);
            
            // Recarregar o motorista
            $motorista->refresh();
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($motorista),
                'message' => 'Motorista atualizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar motorista: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $tenantId = $this->getTenantId();
        
        try {
            $motorista = Motorista::where('tenant_id', $tenantId)->find($id);
            
            if (!$motorista) {
                return response()->json([
                    'success' => false,
                    'error' => 'Motorista não encontrado'
                ], 404);
            }
            
            // Remover arquivos se existirem
            $arquivos = [
                $motorista->foto_url,
                $motorista->foto_carta_url,
                $motorista->foto_passaporte_url
            ];
            
            foreach ($arquivos as $arquivo) {
                if ($arquivo && Storage::disk('public')->exists($arquivo)) {
                    Storage::disk('public')->delete($arquivo);
                }
            }
            
            $motorista->delete();
            
            Log::info('✅ Motorista excluído', [
                'id' => $id,
                'user_id' => Auth::id(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Motorista excluído com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir motorista: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // Método para visualizar documento
    public function visualizarDocumento($id, $tipo)
    {
        $tenantId = $this->getTenantId();
        
        try {
            $motorista = Motorista::where('tenant_id', $tenantId)->find($id);
            
            if (!$motorista) {
                return response()->json([
                    'success' => false,
                    'error' => 'Motorista não encontrado'
                ], 404);
            }
            
            $campo = match($tipo) {
                'foto' => 'foto_url',
                'carta' => 'foto_carta_url',
                'passaporte' => 'foto_passaporte_url',
                default => null
            };
            
            if (!$campo || !$motorista->$campo) {
                return response()->json([
                    'success' => false,
                    'error' => 'Documento não encontrado'
                ], 404);
            }
            
            if (Storage::disk('public')->exists($motorista->$campo)) {
                $mime = Storage::disk('public')->mimeType($motorista->$campo);
                $file = Storage::disk('public')->get($motorista->$campo);
                
                return response($file, 200, [
                    'Content-Type' => $mime,
                    'Content-Disposition' => 'inline; filename="' . basename($motorista->$campo) . '"'
                ]);
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Arquivo não encontrado no storage'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao visualizar documento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}