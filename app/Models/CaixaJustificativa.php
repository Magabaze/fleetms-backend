<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CaixaJustificativa extends Model
{
    use BelongsToTenant;

    protected $table = 'caixa_justificativas';

    protected $fillable = [
        'turno_id',
        'viagem_id',
        'motorista_nome',
        'motorista_id',
        'despesas_ids',
        'tipo',
        'moeda',
        'valor_despesas',
        'valor_recebido',
        'valor_comprovantes',
        'valor_devolvido',
        'diferenca',
        'data_justificativa',
        'observacoes',
        'criado_por',
        'tenant_id',
    ];

    protected $casts = [
        'despesas_ids' => 'array',
        'valor_despesas' => 'decimal:2',
        'valor_recebido' => 'decimal:2',
        'valor_comprovantes' => 'decimal:2',
        'valor_devolvido' => 'decimal:2',
        'diferenca' => 'decimal:2',
        'data_justificativa' => 'datetime',
    ];

    const TIPO_JUSTIFICATIVA = 'justificativa';
    const TIPO_DEVOLUCAO = 'devolucao';

    public function turno(): BelongsTo
    {
        return $this->belongsTo(CaixaTurno::class, 'turno_id');
    }

    public function viagem(): BelongsTo
    {
        return $this->belongsTo(Viagem::class, 'viagem_id');
    }
}