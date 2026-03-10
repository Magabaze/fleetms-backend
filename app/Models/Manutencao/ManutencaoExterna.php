<?php

namespace App\Models\Manutencao;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class ManutencaoExterna extends Model
{
    use BelongsToTenant;
    
    protected $table = 'manutencoes_externas';
    
    protected $fillable = [
        'codigo',
        'ordem_id',
        'ordem_codigo',
        'veiculo',
        'matricula',
        'fornecedor_id',
        'fornecedor_nome',
        'orcamento_id',
        'orcamento_codigo',
        'descricao',
        'status',
        'prioridade',
        'data_saida',
        'data_prevista_retorno',
        'data_retorno',
        'valor_orcado',
        'valor_final',
        'observacoes',
        'tenant_id',
        'criado_por',
    ];
    
    protected $casts = [
        'data_saida' => 'date',
        'data_prevista_retorno' => 'date',
        'data_retorno' => 'date',
        'valor_orcado' => 'decimal:2',
        'valor_final' => 'decimal:2',
    ];
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($externa) {
            if (!$externa->codigo) {
                $lastId = static::max('id') ?? 0;
                $externa->codigo = 'EXT-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);
            }
        });
    }
    
    public function ordem()
    {
        return $this->belongsTo(OrdemTrabalho::class, 'ordem_id');
    }
    
    public function fornecedor()
    {
        return $this->belongsTo(FornecedorManutencao::class, 'fornecedor_id');
    }
    
    public function orcamento()
    {
        return $this->belongsTo(Orcamento::class, 'orcamento_id');
    }
}