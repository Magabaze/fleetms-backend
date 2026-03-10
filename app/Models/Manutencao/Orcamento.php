<?php

namespace App\Models\Manutencao;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Orcamento extends Model
{
    use BelongsToTenant;
    
    protected $table = 'orcamentos';
    
    protected $fillable = [
        'codigo',
        'ordem_id',
        'veiculo',
        'matricula',
        'fornecedor',
        'descricao',
        'valor_orcado',
        'valor_final',
        'status',
        'data_emissao',
        'data_resposta',
        'data_entrada',
        'data_saida',
        'observacoes',
        'tenant_id',
        'criado_por',
    ];
    
    protected $casts = [
        'data_emissao' => 'date',
        'data_resposta' => 'date',
        'data_entrada' => 'date',
        'data_saida' => 'date',
    ];
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($orcamento) {
            if (!$orcamento->codigo) {
                $lastId = static::max('id') ?? 0;
                $orcamento->codigo = 'ORC-' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);
            }
        });
    }
}