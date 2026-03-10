<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CaixaTurno extends Model
{
    use BelongsToTenant;

    protected $table = 'caixa_turnos';

    protected $fillable = [
        'operador_id',
        'operador_nome',
        'saldos',        // ← USAR APENAS ESTE
        'status',
        'data_abertura',
        'data_fechamento',
        'observacoes',
        'tenant_id',
    ];

    protected $casts = [
        'saldos' => 'array',        // ← CAST PARA ARRAY
        'data_abertura' => 'datetime',
        'data_fechamento' => 'datetime',
    ];

    const STATUS_ABERTO = 'aberto';
    const STATUS_FECHADO = 'fechado';

    public function movimentos(): HasMany
    {
        return $this->hasMany(CaixaMovimento::class, 'turno_id');
    }

    public function operador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operador_id');
    }

    public function isAberto(): bool
    {
        return $this->status === self::STATUS_ABERTO;
    }

    public function isFechado(): bool
    {
        return $this->status === self::STATUS_FECHADO;
    }

    public function getSaldoPorMoeda(string $moeda): float
    {
        $saldos = $this->saldos ?? [];
        foreach ($saldos as $saldo) {
            if ($saldo['moeda'] === $moeda) {
                return (float) ($saldo['saldoAtual'] ?? 0);
            }
        }
        return 0;
    }

    public function atualizarSaldo(string $moeda, float $novoSaldo): void
    {
        $saldos = $this->saldos ?? [];
        foreach ($saldos as &$saldo) {
            if ($saldo['moeda'] === $moeda) {
                $saldo['saldoAtual'] = $novoSaldo;
                break;
            }
        }
        $this->saldos = $saldos;
        $this->save();
    }
}