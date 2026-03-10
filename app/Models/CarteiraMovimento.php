<?php
// app/Models/CarteiraMovimento.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarteiraMovimento extends Model
{
    protected $table = 'carteira_movimentos';
    
    protected $fillable = [
        'motorista',
        'origem_id',
        'origem_type',
        'tipo',
        'origem_tipo',
        'descricao',
        'valor',
        'saldo_anterior',
        'saldo_posterior',
        'tenant_id'
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'saldo_anterior' => 'decimal:2',
        'saldo_posterior' => 'decimal:2'
    ];

    public function origem()
    {
        return $this->morphTo();
    }
}