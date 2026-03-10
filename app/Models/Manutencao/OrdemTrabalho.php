<?php

namespace App\Models\Manutencao;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class OrdemTrabalho extends Model
{
    use BelongsToTenant;
    
    protected $table = 'ordens_trabalho';
    
    protected $fillable = [
        'codigo',
        'veiculo',
        'matricula',
        'tipo',
        'descricao',
        'tecnico',
        'status',
        'prioridade',
        'data_criacao',
        'data_prevista',
        'data_inicio',
        'data_fim',
        'custo',
        'fornecedor_id',
        'fornecedor_nome',
        'orcamento_id',
        'local_socorro',
        'km_socorro',
        'observacoes',
        'tenant_id',
        'criado_por',
    ];
    
    protected $casts = [
        'data_criacao' => 'date',
        'data_prevista' => 'date',
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'custo' => 'decimal:2',
        'km_socorro' => 'integer',
    ];
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($ordem) {
            if (!$ordem->codigo) {
                $lastId = static::max('id') ?? 0;
                $prefixo = match($ordem->tipo) {
                    'preventiva' => 'PREV',
                    'corretiva' => 'CORR',
                    'inspecao' => 'INSP',
                    'externa' => 'EXT',
                    'socorro' => 'SOC',
                    default => 'OT'
                };
                $ordem->codigo = $prefixo . '-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}