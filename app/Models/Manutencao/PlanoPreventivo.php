<?php

namespace App\Models\Manutencao;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class PlanoPreventivo extends Model
{
    use BelongsToTenant;
    
    protected $table = 'planos_preventivos';
    
    protected $fillable = [
        'veiculo',
        'matricula',
        'tipo',
        'intervalo_km',
        'intervalo_dias',
        'ultimo_km',
        'km_atual',
        'ultima_data',
        'proxima_data',
        'status',
        'observacoes',
        'tenant_id',
        'criado_por',
    ];
    
    protected $casts = [
        'ultima_data' => 'date',
        'proxima_data' => 'date',
    ];
    
    // Calcular status automaticamente
    public function getStatusAttribute($value)
    {
        if ($value) return $value;
        
        $kmRestantes = ($this->ultimo_km + $this->intervalo_km) - $this->km_atual;
        $diasRestantes = now()->diffInDays(\Carbon\Carbon::parse($this->proxima_data), false);
        
        if ($kmRestantes < 0 || $diasRestantes < 0) return 'vencido';
        if ($kmRestantes < ($this->intervalo_km * 0.1) || $diasRestantes <= 14) return 'alerta';
        return 'ok';
    }
}