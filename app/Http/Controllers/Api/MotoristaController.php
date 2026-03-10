<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Motorista;
use App\Models\Carteira;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

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

    /**
     * FUNÇÃO SIMPLES: Cria carteira automaticamente
     */
    private function criarCarteiraAutomatica($motorista)
    {
        try {
            // Verificar se já existe
            $existe = Carteira::where('motorista', $motorista->nome_completo)
                ->where('tenant_id', $motorista->tenant_id)
                ->first();

            if (!$existe) {
                Carteira::create([
                    'motorista' => $motorista->nome_completo,
                    'saldo' => 0,
                    'total_bonus' => 0,
                    'total_divida' => 0,
                    'ultimo_movimento' => now(),
                    'tenant_id' => $motorista->tenant_id
                ]);

                Log::info('✅ Carteira criada automaticamente', [
                    'motorista' => $motorista->nome_completo
                ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar carteira: ' . $e->getMessage());
        }
    }

    /**
     * Faz upload de arquivo para o Cloudflare R2
     */
    private function fazerUploadR2($file, $pasta, $prefixo, $tenantId)
    {
        if (!$file || !$file->isValid()) {
            return null;
        }

        try {
            $extensao = $file->getClientOriginalExtension();
            $nomeArquivo = $prefixo . '_' . time() . '_' . Str::random(10) . '.' . $extensao;
            $caminhoR2 = "{$pasta}/{$tenantId}/{$nomeArquivo}";

            Storage::disk('r2')->put(
                $caminhoR2,
                file_get_contents($file),
                ['ContentType' => $file->getMimeType()]
            );

            return $caminhoR2;

        } catch (\Exception $e) {
            Log::error('❌ Erro no upload R2: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Deleta arquivo do R2
     */
    private function deletarArquivoR2($caminho)
    {
        if ($caminho && Storage::disk('r2')->exists($caminho)) {
            try {
                Storage::disk('r2')->delete($caminho);
                return true;
            } catch (\Exception $e) {
                Log::error('❌ Erro ao deletar do R2: ' . $e->getMessage());
                return false;
            }
        }
        return true;
    }

    /**
     * Converte o modelo do banco para o formato camelCase do Frontend
     */
    private function paraCamelCase($motorista)
    {
        return [
            'id'                  => $motorista->id,
            'nomeCompleto'        => $motorista->nome_completo,
            'numeroCarta'         => $motorista->numero_carta,
            'numeroPassaporte'    => $motorista->numero_passaporte,
            'nacionalidade'       => $motorista->nacionalidade,
            'telefone'            => $motorista->telefone,
            'telefoneAlternativo' => $motorista->telefone_alternativo,
            'email'               => $motorista->email,
            'endereco'            => $motorista->endereco,
            'tipoLicenca'         => $motorista->tipo_licenca,
            'validadeLicenca'     => $motorista->validade_licenca ? Carbon::parse($motorista->validade_licenca)->format('Y-m-d') : null,
            'validadePassaporte'  => $motorista->validade_passaporte ? Carbon::parse($motorista->validade_passaporte)->format('Y-m-d') : null,
            'status'              => $motorista->status,
            'observacoes'         => $motorista->observacoes,
            'criadoPor'           => $motorista->criado_por,
            'createdAt'           => $motorista->created_at?->toISOString(),
            'updatedAt'           => $motorista->updated_at?->toISOString(),
            'fotoUrl'             => $motorista->foto_url ? Storage::disk('r2')->url($motorista->foto_url) : null,
            'fotoCartaUrl'        => $motorista->foto_carta_url ? Storage::disk('r2')->url($motorista->foto_carta_url) : null,
            'fotoPassaporteUrl'   => $motorista->foto_passaporte_url ? Storage::disk('r2')->url($motorista->foto_passaporte_url) : null,
        ];
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId();

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

            // 👉 GARANTIR QUE TODOS OS MOTORISTAS TENHAM CARTEIRA
            foreach ($motoristas as $motorista) {
                $this->criarCarteiraAutomatica($motorista);
            }

            $motoristasCamelCase = $motoristas->map(function ($motorista) {
                return $this->paraCamelCase($motorista);
            });

            return response()->json([
                'success' => true,
                'data' => $motoristasCamelCase->toArray(),
                'pagination' => [
                    'page' => $motoristas->currentPage(),
                    'limit' => $perPage,
                    'total' => $motoristas->total(),
                    'totalPages' => $motoristas->lastPage(),
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

        // 👉 VALIDAÇÃO SIMPLES - SÓ OBRIGATÓRIOS
        $validator = Validator::make($request->all(), [
            'nomeCompleto' => 'required|string|max:255',
            'numeroCarta' => 'required|string|max:50',
            'nacionalidade' => 'required|string|max:50',
            'telefone' => 'required|string|max:20',
            'tipoLicenca' => 'required|in:A,B,C,D,E',
            'validadeLicenca' => 'required|date',
            'status' => 'required|in:Ativo,Inativo,Férias,Licença',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $dados = [
                'nome_completo'       => $request->nomeCompleto,
                'numero_carta'        => $request->numeroCarta,
                'numero_passaporte'   => $request->numeroPassaporte,
                'nacionalidade'       => $request->nacionalidade,
                'telefone'            => $request->telefone,
                'telefone_alternativo'=> $request->telefoneAlternativo,
                'email'               => $request->email,
                'endereco'            => $request->endereco,
                'tipo_licenca'        => $request->tipoLicenca,
                'validade_licenca'    => $request->validadeLicenca,
                'validade_passaporte' => $request->validadePassaporte,
                'status'              => $request->status,
                'observacoes'         => $request->observacoes,
                'criado_por'          => $user->name ?? 'Sistema',
                'tenant_id'           => $tenantId,
            ];

            // Upload das fotos
            if ($request->hasFile('foto')) {
                $dados['foto_url'] = $this->fazerUploadR2($request->file('foto'), 'motoristas/fotos', 'foto', $tenantId);
            }

            if ($request->hasFile('fotoCarta')) {
                $dados['foto_carta_url'] = $this->fazerUploadR2($request->file('fotoCarta'), 'motoristas/documentos/cartas', 'carta', $tenantId);
            }

            if ($request->hasFile('fotoPassaporte')) {
                $dados['foto_passaporte_url'] = $this->fazerUploadR2($request->file('fotoPassaporte'), 'motoristas/documentos/passaportes', 'passaporte', $tenantId);
            }

            $motorista = Motorista::create($dados);

            // 👉 CRIAR CARTEIRA AUTOMATICAMENTE!
            $this->criarCarteiraAutomatica($motorista);

            return response()->json([
                'success' => true,
                'data'    => $this->paraCamelCase($motorista),
                'message' => 'Motorista criado com sucesso!'
            ], 201);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar motorista: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar motorista: ' . $e->getMessage()
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

            // 👉 GARANTIR CARTEIRA
            $this->criarCarteiraAutomatica($motorista);

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

        try {
            $motorista = Motorista::where('tenant_id', $tenantId)->find($id);

            if (!$motorista) {
                return response()->json([
                    'success' => false,
                    'error' => 'Motorista não encontrado'
                ], 404);
            }

            // 👉 VALIDAÇÃO SIMPLES
            $validator = Validator::make($request->all(), [
                'nomeCompleto' => 'required|string|max:255',
                'numeroCarta' => 'required|string|max:50',
                'nacionalidade' => 'required|string|max:50',
                'telefone' => 'required|string|max:20',
                'tipoLicenca' => 'required|in:A,B,C,D,E',
                'validadeLicenca' => 'required|date',
                'status' => 'required|in:Ativo,Inativo,Férias,Licença',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }

            $dadosAtualizacao = [
                'nome_completo'       => $request->nomeCompleto,
                'numero_carta'        => $request->numeroCarta,
                'numero_passaporte'   => $request->numeroPassaporte,
                'nacionalidade'       => $request->nacionalidade,
                'telefone'            => $request->telefone,
                'telefone_alternativo'=> $request->telefoneAlternativo,
                'email'               => $request->email,
                'endereco'            => $request->endereco,
                'tipo_licenca'        => $request->tipoLicenca,
                'validade_licenca'    => $request->validadeLicenca,
                'validade_passaporte' => $request->validadePassaporte,
                'status'              => $request->status,
                'observacoes'         => $request->observacoes,
            ];

            // Processar fotos
            if ($request->hasFile('foto')) {
                $this->deletarArquivoR2($motorista->foto_url);
                $dadosAtualizacao['foto_url'] = $this->fazerUploadR2($request->file('foto'), 'motoristas/fotos', 'foto', $tenantId);
            }

            if ($request->hasFile('fotoCarta')) {
                $this->deletarArquivoR2($motorista->foto_carta_url);
                $dadosAtualizacao['foto_carta_url'] = $this->fazerUploadR2($request->file('fotoCarta'), 'motoristas/documentos/cartas', 'carta', $tenantId);
            }

            if ($request->hasFile('fotoPassaporte')) {
                $this->deletarArquivoR2($motorista->foto_passaporte_url);
                $dadosAtualizacao['foto_passaporte_url'] = $this->fazerUploadR2($request->file('fotoPassaporte'), 'motoristas/documentos/passaportes', 'passaporte', $tenantId);
            }

            $motorista->update($dadosAtualizacao);
            $motorista->refresh();

            // 👉 GARANTIR CARTEIRA
            $this->criarCarteiraAutomatica($motorista);

            return response()->json([
                'success' => true,
                'data'    => $this->paraCamelCase($motorista),
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

            // Deleta todos os arquivos relacionados
            $this->deletarArquivoR2($motorista->foto_url);
            $this->deletarArquivoR2($motorista->foto_carta_url);
            $this->deletarArquivoR2($motorista->foto_passaporte_url);

            $motorista->delete();

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
}