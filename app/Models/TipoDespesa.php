<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class TipoDespesa extends Model
{
    use BelongsToTenant;
    
    protected $table = 'tipos_despesa';
    
    protected $fillable = [
        'nome',
        'descricao',
        'cor',
        'requer_comprovante',
        'criado_por',
        'tenant_id',
    ];
    
    protected $casts = [
        'requer_comprovante' => 'boolean',
    ];
    
    // Escopo para evitar duplicatas no mesmo tenant
    public function scopeNoTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
    
    // Relacionamento com despesas
    public function despesas()
    {
        return $this->hasMany(DespesaMotorista::class, 'tipo', 'nome');
    }
}