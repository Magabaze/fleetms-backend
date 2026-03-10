<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CaixaRequisicao extends Model
{
    use BelongsToTenant;

    protected $table = 'caixa_requisicoes';

    protected $fillable = [
        'viagem_id',
        'motorista_nome',
        'motorista_id',
        'valor',
        'descricao',
        'data_requisicao',
        'status',
        'aprovado_por',
        'data_aprovacao',
        'observacoes',
        'tenant_id',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_requisicao' => 'datetime',
        'data_aprovacao' => 'datetime',
    ];

    const STATUS_PENDENTE = 'pendente';
    const STATUS_APROVADO = 'aprovado';
    const STATUS_PAGO = 'pago';
    const STATUS_REJEITADO = 'rejeitado';

    public function viagem(): BelongsTo
    {
        return $this->belongsTo(Viagem::class, 'viagem_id');
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class, 'motorista_id');
    }

    public function movimentos(): MorphMany
    {
        return $this->morphMany(CaixaMovimento::class, 'referencia');
    }

    public function isPendente(): bool
    {
        return $this->status === self::STATUS_PENDENTE;
    }

    public function isAprovado(): bool
    {
        return $this->status === self::STATUS_APROVADO;
    }

    public function isPago(): bool
    {
        return $this->status === self::STATUS_PAGO;
    }
}