<?php
// app/Models/Combustivel/Tanque.php

namespace App\Models\Combustivel;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Tanque extends Model
{
    use BelongsToTenant;
    
    protected $table = 'tanques';
    
    protected $fillable = [
        'nome',
        'codigo',
        'tipo_combustivel',
        'capacidade_total',
        'nivel_atual',
        'unidade_medida',
        'localizacao',
        'status',
        'alerta_minimo',
        'alerta_critico',
        'observacoes',
        'criado_por',
        'tenant_id',
    ];
    
    protected $casts = [
        'capacidade_total' => 'decimal:2',
        'nivel_atual' => 'decimal:2',
        'alerta_minimo' => 'integer',
        'alerta_critico' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    // Tipos de combustível disponíveis
    const TIPOS_COMBUSTIVEL = [
        'gasolina_95' => 'Gasolina 95',
        'gasolina_98' => 'Gasolina 98',
        'diesel' => 'Diesel',
        'diesel_premium' => 'Diesel Premium',
        'etanol' => 'Etanol',
        'gpl' => 'GPL',
    ];
    
    // Status disponíveis
    const STATUS = [
        'ativo' => 'Ativo',
        'inativo' => 'Inativo',
        'manutencao' => 'Em Manutenção',
    ];
    
    // Unidades de medida
    const UNIDADES_MEDIDA = [
        'litros' => 'Litros (L)',
        'm3' => 'Metros Cúbicos (m³)',
        'galoes' => 'Galões',
    ];
    
    public function getStatusAttribute($value)
    {
        return $value ?? 'ativo';
    }
    
    public function getTipoCombustivelLabelAttribute()
    {
        return self::TIPOS_COMBUSTIVEL[$this->tipo_combustivel] ?? $this->tipo_combustivel;
    }
    
    public function getStatusLabelAttribute()
    {
        return self::STATUS[$this->status] ?? $this->status;
    }
    
    public function getPercentualOcupacaoAttribute()
    {
        if ($this->capacidade_total <= 0) {
            return 0;
        }
        return round(($this->nivel_atual / $this->capacidade_total) * 100, 1);
    }
    
    public function getNivelDisponivelAttribute()
    {
        return $this->capacidade_total - $this->nivel_atual;
    }
    
    public function getNivelAlertaAttribute()
    {
        $percentual = $this->percentual_ocupacao;
        
        if ($percentual <= $this->alerta_critico) {
            return 'critico';
        }
        
        if ($percentual <= $this->alerta_minimo) {
            return 'alerta';
        }
        
        if ($percentual >= 80) {
            return 'excelente';
        }
        
        return 'normal';
    }
    
    public function getCorNivelAttribute()
    {
        switch ($this->nivel_alerta) {
            case 'critico':
                return 'bg-red-500';
            case 'alerta':
                return 'bg-yellow-500';
            case 'excelente':
                return 'bg-blue-500';
            default:
                return 'bg-green-500';
        }
    }
    
    // Scopes
    public function scopeAtivo($query)
    {
        return $query->where('status', 'ativo');
    }
    
    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo_combustivel', $tipo);
    }
    
    public function scopeNivelCritico($query)
    {
        return $query->whereRaw('(nivel_atual / capacidade_total) * 100 <= alerta_critico');
    }
    
    public function scopeNivelAlerta($query)
    {
        return $query->whereRaw('(nivel_atual / capacidade_total) * 100 <= alerta_minimo')
                     ->whereRaw('(nivel_atual / capacidade_total) * 100 > alerta_critico');
    }
}