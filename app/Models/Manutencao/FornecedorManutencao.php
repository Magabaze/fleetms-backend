<?php

namespace App\Models\Manutencao;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class FornecedorManutencao extends Model
{
    use BelongsToTenant;
    
    protected $table = 'fornecedores_manutencao';
    
    protected $fillable = [
        'nome',
        'tipo',
        'especialidade',
        'contacto',
        'email',
        'morada',
        'avaliacao',
        'total_servicos',
        'ultimo_servico',
        'status',
        'tempo_medio_resposta',
        'observacoes',
        'tenant_id',
        'criado_por',
    ];
    
    protected $casts = [
        'especialidade' => 'array',
        'avaliacao' => 'float',
        'ultimo_servico' => 'date',
    ];
}