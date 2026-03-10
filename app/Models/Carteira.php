<?php
// app/Models/Carteira.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Carteira extends Model
{
    protected $table = 'carteiras';
    
    protected $fillable = [
        'motorista',
        'saldo',
        'total_bonus',
        'total_divida',
        'ultimo_movimento',
        'tenant_id'
    ];

    protected $casts = [
        'saldo' => 'decimal:2',
        'total_bonus' => 'decimal:2',
        'total_divida' => 'decimal:2',
        'ultimo_movimento' => 'datetime'
    ];

    public function movimentos()
    {
        return $this->hasMany(CarteiraMovimento::class, 'motorista', 'motorista');
    }

    public function pagamentos()
    {
        return $this->hasMany(CarteiraPagamento::class, 'motorista', 'motorista');
    }
}