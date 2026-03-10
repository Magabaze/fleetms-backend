<?php
// app/Models/CarteiraPagamento.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarteiraPagamento extends Model
{
    protected $table = 'carteira_pagamentos';
    
    protected $fillable = [
        'motorista',
        'valor',
        'desconto_aplicado',
        'tipo_pagamento',
        'observacoes',
        'tenant_id'
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'desconto_aplicado' => 'decimal:2'
    ];
}