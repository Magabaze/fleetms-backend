<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CaixaMovimento extends Model
{
    use BelongsToTenant;

    protected $table = 'caixa_movimentos';

    protected $fillable = [
        'turno_id',
        'tipo',
        'valor',
        'descricao',
        'referencia_id',
        'referencia_tipo',
        'data_movimento',
        'saldo_anterior',
        'saldo_posterior',
        'tenant_id',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'saldo_anterior' => 'decimal:2',
        'saldo_posterior' => 'decimal:2',
        'data_movimento' => 'datetime',
    ];

    const TIPO_ENTRADA = 'entrada';
    const TIPO_SAIDA = 'saida';

    public function turno(): BelongsTo
    {
        return $this->belongsTo(CaixaTurno::class, 'turno_id');
    }

    public function referencia()
    {
        return $this->morphTo('referencia', 'referencia_tipo', 'referencia_id');
    }
}