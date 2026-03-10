<?php

namespace App\Models\Manutencao;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Peca extends Model
{
    use BelongsToTenant;
    
    protected $table = 'pecas';
    
    protected $fillable = [
        'codigo',
        'nome',
        'categoria',
        'stock_atual',
        'stock_minimo',
        'unidade',
        'preco_unitario',
        'fornecedor',
        'ultima_entrada',
        'observacoes',
        'tenant_id',
        'criado_por',
    ];
    
    protected $casts = [
        'ultima_entrada' => 'date',
    ];
    
    // Calcular status automaticamente
    public function getStatusAttribute()
    {
        if ($this->stock_atual <= 0) return 'critico';
        if ($this->stock_atual <= $this->stock_minimo) return 'alerta';
        return 'ok';
    }
}