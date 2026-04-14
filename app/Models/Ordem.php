<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ordem extends Model
{
    use HasFactory;

    protected $table = 'ordens';

    protected $fillable = [
        'order_numero',
        'tipo_transito',
        'cliente_id',
        'consignee_id',
        'expedidor_id',
        'origem',
        'destino',
        'commodity',
        'tipo_carga',
        'status',
        'created_date',
        'previsao_carregamento',
        'numero_bl',
        'shipping_line',
        'fronteira',
        'agente_fronteira',
        'taxa_cliente_id',
        'moeda_fatura',
        'peso_total',
        'volume_total',
        'observacoes',
        'criado_por',
        'aprovado_por',
        'aprovado_em',
        'empresa',
        'tenant_id',
        'rate_id', // ADICIONADO
    ];

    protected $casts = [
        'created_date' => 'date',
        'previsao_carregamento' => 'date',
        'aprovado_em' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relacionamentos
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function consignee(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'consignee_id');
    }

    public function expedidor(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'expedidor_id');
    }

    public function rate(): BelongsTo
    {
        return $this->belongsTo(Rate::class, 'rate_id');
    }

    public function containers(): HasMany
    {
        return $this->hasMany(Container::class, 'ordem_id');
    }

    public function breakBulkItems(): HasMany
    {
        return $this->hasMany(BreakBulkItem::class, 'ordem_id');
    }
}