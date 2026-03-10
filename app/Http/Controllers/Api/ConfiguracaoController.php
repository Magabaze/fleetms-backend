<?php
// app/Http/Controllers/Api/ConfiguracaoController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ConfiguracaoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // =================== PERFIL ===================
    public function getPerfil()
    {
        try {
            $user = Auth::user();
            $isAdmin = $user->isSuperAdmin();

            return response()->json([
                'success' => true,
                'data' => [
                    'nome' => $user->name,
                    'email' => $user->email,
                    'telefone' => $user->telefone,
                    'cargo' => $user->cargo,
                    'departamento' => $user->departamento,
                    'endereco' => $user->endereco,
                    'bio' => $user->bio,
                    'isAdmin' => $isAdmin,
                    'roleNome' => $user->role ? $user->role->nome : null,
                    'idioma' => $user->idioma,
                    'fusoHorario' => $user->fuso_horario,
                    'tenantId' => $user->tenant_id,
                    'createdAt' => $user->created_at->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar perfil: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro ao buscar perfil'], 500);
        }
    }

    public function updatePerfil(Request $request)
    {
        try {
            $user = Auth::user();
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'nome' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $user->id,
                'telefone' => 'nullable|string|max:20',
                'idioma' => 'required|in:pt,en,es',
                'fusoHorario' => 'required|string',
            ]);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'error' => 'Erro de validação'], 422);
            }

            $user->update([
                'name' => $request->nome,
                'email' => $request->email,
                'telefone' => $request->telefone,
                'idioma' => $request->idioma,
                'fuso_horario' => $request->fusoHorario,
            ]);

            return response()->json([
                'success' => true,
                'data' => $user->only('name', 'email', 'telefone', 'idioma', 'fuso_horario'),
                'message' => 'Atualizado'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =================== EMPRESA ===================
    public function getEmpresa()
    {
        try {
            $user = Auth::user();
            $isAdmin = $user->isSuperAdmin();

            if (!$isAdmin) {
                return response()->json(['success' => false, 'error' => 'Apenas admins podem visualizar.'], 403);
            }

            $empresa = Empresa::where('tenant_id', $user->tenant_id)->first();
            if (!$empresa) {
                $empresa = Empresa::create([
                    'nome' => 'Nome da Empresa',
                    'tenant_id' => $user->tenant_id
                ]);
            }

            $logoUrl = $empresa->logo_url ? Storage::disk('r2')->url($empresa->logo_url) : null;

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $empresa->id,
                    'nome' => $empresa->nome,
                    'cnpj' => $empresa->cnpj,
                    'email' => $empresa->email,
                    'telefone' => $empresa->telefone,
                    'website' => $empresa->website,
                    'endereco' => $empresa->endereco,
                    'cidade' => $empresa->cidade,
                    'estado' => $empresa->estado,
                    'cep' => $empresa->cep,
                    'setor' => $empresa->setor,
                    'funcionarios' => $empresa->funcionarios,
                    'descricao' => $empresa->descricao,
                    'fundacao' => $empresa->fundacao,
                    'missao' => $empresa->missao,
                    'visao' => $empresa->visao,
                    'moedaPadrao' => $empresa->moeda_padrao,
                    'fusoHorario' => $empresa->fuso_horario,
                    'tenantId' => $empresa->tenant_id,
                    'logoUrl' => $logoUrl
                ],
                'permissions' => [
                    'canEdit' => $isAdmin,
                    'isAdmin' => $isAdmin
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar empresa: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function updateEmpresa(Request $request)
    {
        try {
            $user = Auth::user();
            $isAdmin = $user->isSuperAdmin();
            if (!$isAdmin) return response()->json(['success' => false, 'error' => 'Apenas admins podem editar.'], 403);

            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'nome' => 'required|string|max:255',
                'cnpj' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'telefone' => 'nullable|string|max:20',
                'website' => 'nullable|string|max:255',
                'endereco' => 'nullable|string|max:500',
                'cidade' => 'nullable|string|max:100',
                'estado' => 'nullable|string|max:2',
                'cep' => 'nullable|string|max:10',
                'setor' => 'nullable|string|max:100',
                'funcionarios' => 'nullable|string|max:50',
                'descricao' => 'nullable|string|max:1000',
                'fundacao' => 'nullable|string|max:4',
                'missao' => 'nullable|string|max:1000',
                'visao' => 'nullable|string|max:1000',
                'moedaPadrao' => 'nullable|in:BRL,USD,EUR',
                'fusoHorario' => 'nullable|string|max:50',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }

            $empresa = Empresa::where('tenant_id', $user->tenant_id)->first();
            if (!$empresa) $empresa = Empresa::create(['tenant_id' => $user->tenant_id]);

            $logoUrl = $empresa->logo_url;

            if ($request->hasFile('logo')) {
                $file = $request->file('logo');
                $tenantId = $user->tenant_id;
                $path = Storage::disk('r2')->putFile("empresas/{$tenantId}", $file, ['ContentType' => $file->getMimeType()]);
                $logoUrl = $path;
            } elseif ($request->input('logoUrl') === '') {
                $logoUrl = null;
            }

            $empresa->update([
                'nome' => $request->nome,
                'cnpj' => $request->cnpj,
                'email' => $request->email,
                'telefone' => $request->telefone,
                'website' => $request->website,
                'endereco' => $request->endereco,
                'cidade' => $request->cidade,
                'estado' => $request->estado,
                'cep' => $request->cep,
                'setor' => $request->setor,
                'funcionarios' => $request->funcionarios,
                'descricao' => $request->descricao,
                'fundacao' => $request->fundacao,
                'missao' => $request->missao,
                'visao' => $request->visao,
                'moeda_padrao' => $request->moedaPadrao ?? 'BRL',
                'fuso_horario' => $request->fusoHorario ?? 'America/Sao_Paulo',
                'logo_url' => $logoUrl,
            ]);

            $urlRetorno = $empresa->logo_url ? Storage::disk('r2')->url($empresa->logo_url) : null;

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $empresa->id,
                    'nome' => $empresa->nome,
                    'cnpj' => $empresa->cnpj,
                    'email' => $empresa->email,
                    'telefone' => $empresa->telefone,
                    'website' => $empresa->website,
                    'endereco' => $empresa->endereco,
                    'cidade' => $empresa->cidade,
                    'estado' => $empresa->estado,
                    'cep' => $empresa->cep,
                    'setor' => $empresa->setor,
                    'funcionarios' => $empresa->funcionarios,
                    'descricao' => $empresa->descricao,
                    'fundacao' => $empresa->fundacao,
                    'missao' => $empresa->missao,
                    'visao' => $empresa->visao,
                    'moedaPadrao' => $empresa->moeda_padrao,
                    'fusoHorario' => $empresa->fuso_horario,
                    'tenantId' => $empresa->tenant_id,
                    'logoUrl' => $urlRetorno
                ],
                'message' => 'Dados atualizados com sucesso!'
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar empresa: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}