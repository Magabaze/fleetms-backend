<?php

namespace App\Observers;

use App\Models\Empresa;
use App\Models\EmpresaCodigo;
use Illuminate\Support\Facades\Log;

class EmpresaObserver
{
    /**
     * Handle the Empresa "created" event.
     */
    public function created(Empresa $empresa): void
    {
        try {
            Log::info('👶 Nova empresa criada, gerando código prefixo...', [
                'empresa_id' => $empresa->id,
                'empresa_nome' => $empresa->nome,
                'tenant_id' => $empresa->tenant_id
            ]);
            
            // Verificar se já tem um código ativo
            $codigoExistente = EmpresaCodigo::where('tenant_id', $empresa->tenant_id)
                ->where('is_active', true)
                ->first();
            
            if ($codigoExistente) {
                Log::info('✅ Empresa já possui código prefixo', [
                    'empresa_id' => $empresa->id,
                    'codigo' => $codigoExistente->codigo_prefixo
                ]);
                return;
            }
            
            // Gerar prefixo baseado no nome da empresa
            $prefixo = $this->gerarPrefixoAutomatico($empresa->nome);
            
            // Criar registro na tabela empresa_codigos
            $empresaCodigo = EmpresaCodigo::create([
                'tenant_id' => $empresa->tenant_id,
                'codigo_prefixo' => $prefixo,
                'descricao' => 'Gerado automaticamente para ' . $empresa->nome,
                'is_active' => true,
            ]);
            
            Log::info('✅ Código prefixo gerado automaticamente', [
                'empresa_id' => $empresa->id,
                'empresa_nome' => $empresa->nome,
                'tenant_id' => $empresa->tenant_id,
                'codigo_prefixo' => $prefixo,
                'empresa_codigo_id' => $empresaCodigo->id
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao gerar código prefixo automaticamente: ' . $e->getMessage(), [
                'empresa_id' => $empresa->id,
                'empresa_nome' => $empresa->nome
            ]);
        }
    }
    
    /**
     * Gerar prefixo automático baseado no nome da empresa
     */
    private function gerarPrefixoAutomatico($nomeEmpresa): string
    {
        // 1. Remover caracteres especiais e espaços
        $nomeLimpo = preg_replace('/[^a-zA-Z0-9]/', '', $nomeEmpresa);
        
        // 2. Pegar as primeiras letras (máximo 3)
        $prefixo = strtoupper(substr($nomeLimpo, 0, 3));
        
        // 3. Se nome muito curto, completar
        if (strlen($prefixo) < 2) {
            $prefixo = str_pad($prefixo, 2, 'X', STR_PAD_RIGHT);
        }
        
        // 4. Verificar se já existe e incrementar se necessário
        $prefixoOriginal = $prefixo;
        $contador = 1;
        
        while (EmpresaCodigo::where('codigo_prefixo', $prefixo)->exists()) {
            // Tentar com números (ex: ABC1, ABC2)
            $prefixo = $prefixoOriginal . $contador;
            $contador++;
            
            // Limitar a 5 caracteres
            if (strlen($prefixo) > 5) {
                $prefixo = substr($prefixo, 0, 5);
            }
            
            // Prevenir loop infinito
            if ($contador > 50) {
                $prefixo = $prefixoOriginal . rand(100, 999);
                break;
            }
        }
        
        return $prefixo;
    }
    
    /**
     * Handle the Empresa "updated" event.
     */
    public function updated(Empresa $empresa): void
    {
        // Opcional: atualizar descrição do código se nome mudar
        if ($empresa->isDirty('nome')) {
            try {
                $empresaCodigo = EmpresaCodigo::where('tenant_id', $empresa->tenant_id)
                    ->where('is_active', true)
                    ->first();
                    
                if ($empresaCodigo) {
                    $empresaCodigo->update([
                        'descricao' => 'Gerado automaticamente para ' . $empresa->nome
                    ]);
                    
                    Log::info('🔄 Descrição do código prefixo atualizada', [
                        'empresa_id' => $empresa->id,
                        'novo_nome' => $empresa->nome,
                        'codigo' => $empresaCodigo->codigo_prefixo
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('❌ Erro ao atualizar descrição do código: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Handle the Empresa "deleted" event.
     */
    public function deleted(Empresa $empresa): void
    {
        // Opcional: desativar códigos quando empresa é deletada
        try {
            $count = EmpresaCodigo::where('tenant_id', $empresa->tenant_id)
                ->update(['is_active' => false]);
                
            if ($count > 0) {
                Log::info('🗑️ Códigos prefixo desativados para empresa deletada', [
                    'empresa_id' => $empresa->id,
                    'codigos_desativados' => $count
                ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Erro ao desativar códigos prefixo: ' . $e->getMessage());
        }
    }
}