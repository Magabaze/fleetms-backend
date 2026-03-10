<?php
// app/Models/OrdemFaturacao.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class OrdemFaturacao extends Model
{
    use BelongsToTenant;
    
    protected $table = 'ordens_faturacao';
    
    protected $fillable = [
        'codigo',
        'viagem_id',
        'cliente',
        'motorista',
        'origem',
        'destino',
        'valor',
        'data_viagem',
        'status',
        'observacoes',
        'criado_por',
        'tenant_id',
    ];
    
    protected $casts = [
        'valor' => 'decimal:2',
        'data_viagem' => 'date',
    ];
    
    const STATUS = [
        'pendente' => 'Pendente',
        'processado' => 'Processado',
        'cancelado' => 'Cancelado'
    ];
    
    public function viagem()
    {
        return $this->belongsTo(Viagem::class, 'viagem_id');
    }
    
    public function notas()
    {
        return $this->hasMany(NotaFiscal::class, 'ordem_id');
    }
}