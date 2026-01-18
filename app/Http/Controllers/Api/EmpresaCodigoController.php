<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use Illuminate\Support\Facades\Log;

class EmpresaCodigo extends Model
{
    use BelongsToTenant;
    
    protected $table = 'empresa_codigos';
    
    protected $fillable = [
        'tenant_id',
        'codigo_prefixo',
        'descricao',
        'is_active'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
    ];
    
    /**
     * Gerar código prefixo para uma empresa (tenant)
     */
    public static function gerarParaEmpresa($tenantId, $nomeEmpresa)
    {
        try {
            // Verificar se já existe
            $existente = self::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->first();
                
            if ($existente) {
                return $existente;
            }
            
            // Gerar prefixo único
            $prefixo = self::gerarPrefixoUnico($nomeEmpresa);
            
            // Criar registro
            $empresaCodigo = self::create([
                'tenant_id' => $tenantId,
                'codigo_prefixo' => $prefixo,
                'descricao' => 'Gerado para ' . $nomeEmpresa,
                'is_active' => true,
            ]);
            
            Log::info('✅ Código prefixo gerado', [
                'tenant_id' => $tenantId,
                'nome_empresa' => $nomeEmpresa,
                'codigo' => $prefixo
            ]);
            
            return $empresaCodigo;
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao gerar código prefixo: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Gerar prefixo único baseado no nome
     */
    public static function gerarPrefixoUnico($nomeEmpresa)
    {
        // Remove caracteres especiais
        $nomeLimpo = preg_replace('/[^a-zA-Z0-9]/', '', $nomeEmpresa);
        
        // Pega primeiras letras
        $prefixo = strtoupper(substr($nomeLimpo, 0, 3));
        
        // Completa se necessário
        if (strlen($prefixo) < 2) {
            $prefixo = str_pad($prefixo, 2, 'X', STR_PAD_RIGHT);
        }
        
        // Verifica unicidade
        $original = $prefixo;
        $contador = 1;
        
        while (self::where('codigo_prefixo', $prefixo)->exists()) {
            $prefixo = $original . $contador;
            $contador++;
            
            if (strlen($prefixo) > 5) {
                $prefixo = substr($prefixo, 0, 5);
            }
            
            if ($contador > 100) {
                // Fallback: prefixo + timestamp
                $prefixo = $original . substr(time(), -3);
                break;
            }
        }
        
        return $prefixo;
    }
    
    /**
     * Verificar disponibilidade de um código
     */
    public static function verificarDisponibilidade($codigo)
    {
        $codigo = strtoupper(trim($codigo));
        
        // Validação básica
        if (!preg_match('/^[A-Z]{2,5}$/', $codigo)) {
            return [
                'disponivel' => false,
                'mensagem' => 'Código inválido. Use 2-5 letras maiúsculas.'
            ];
        }
        
        $existente = self::where('codigo_prefixo', $codigo)
            ->where('is_active', true)
            ->first();
        
        return [
            'disponivel' => !$existente,
            'mensagem' => $existente 
                ? 'Código já em uso pela empresa: ' . ($existente->descricao ?? 'N/A')
                : 'Código disponível!',
            'codigo' => $codigo
        ];
    }
}