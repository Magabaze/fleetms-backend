<?php
// app/Models/Combustivel/AbastecimentoInterno.php

namespace App\Models\Combustivel;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class AbastecimentoInterno extends Model
{
    use BelongsToTenant;
    
    protected $table = 'abastecimentos_internos';
    
    protected $fillable = [
        'numero',
        'viagem_id',
        'veiculo_id',
        'motorista_id',
        'tipo_combustivel',
        'quantidade',
        'odometro',
        'data_abastecimento',
        'hora_abastecimento',
        'responsavel',
        'status',
        'observacoes',
        'tanque_id',          // ✅ SÓ PRECISA ADICIONAR ESTA LINHA
        'tenant_id',
    ];
    
    protected $casts = [
        'data_abastecimento' => 'date',
        'quantidade' => 'decimal:2',
        'odometro' => 'integer',
    ];
    
    public function camiao()
    {
        return $this->belongsTo(\App\Models\Camiao::class, 'veiculo_id');
    }
    
    public function motorista()
    {
        return $this->belongsTo(\App\Models\Motorista::class, 'motorista_id');
    }
    
    public function viagem()
    {
        return $this->belongsTo(\App\Models\Viagem::class, 'viagem_id');
    }
    
    // ✅ ADICIONAR O RELACIONAMENTO COM TANQUE
    public function tanque()
    {
        return $this->belongsTo(Tanque::class, 'tanque_id');
    }
    
    // ✅ ADICIONAR ACCESSORS PARA O TANQUE
    public function getTanqueNomeAttribute()
    {
        return $this->tanque->nome ?? null;
    }
    
    public function getTanqueCodigoAttribute()
    {
        return $this->tanque->codigo ?? null;
    }
}