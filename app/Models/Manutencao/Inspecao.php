<?php

namespace App\Models\Manutencao;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Inspecao extends Model
{
    use BelongsToTenant;
    
    protected $table = 'inspecoes';
    
    protected $fillable = [
        'veiculo',
        'matricula',
        'tipo',
        'entidade',
        'data_ultima',
        'data_validade',
        'status',
        'resultado',
        'observacoes',
        'tenant_id',
        'criado_por',
    ];
    
    protected $casts = [
        'data_ultima' => 'date',
        'data_validade' => 'date',
    ];
    
    // Calcular status automaticamente
    public function getStatusAttribute($value)
    {
        if ($value) return $value;
        
        $hoje = now();
        $validade = \Carbon\Carbon::parse($this->data_validade);
        $diasRestantes = $hoje->diffInDays($validade, false);
        
        if ($diasRestantes < 0) return 'vencido';
        if ($diasRestantes <= 30) return 'alerta';
        return 'valido';
    }
}