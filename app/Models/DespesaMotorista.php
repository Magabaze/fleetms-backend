<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class DespesaMotorista extends Model
{
    use BelongsToTenant;
    
    protected $table = 'despesas';
    
    protected $fillable = [
        'distancia_id',
        'tipo',
        'descricao',
        'valor_estimado',
        'moeda',
        'requer_comprovante',
        'criado_por',
        'tenant_id',
    ];
    
    protected $casts = [
        'valor_estimado' => 'decimal:2',
        'requer_comprovante' => 'boolean',
    ];
    
    // Relacionamento com Distancia
    public function distancia()
    {
        return $this->belongsTo(Distancia::class, 'distancia_id');
    }
    
    // Relacionamento com TipoDespesa (pelo nome do tipo)
    public function tipoDespesa()
    {
        return $this->belongsTo(TipoDespesa::class, 'tipo', 'nome');
    }
}