<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class PagamentoMotorista extends Model
{
    use BelongsToTenant;
    protected $table = 'pagamentos_motoristas';
    protected $fillable = ['motorista', 'total_bonus', 'total_descontos', 'valor_liquido', 'data_pagamento', 'status', 'observacoes', 'tenant_id'];
    protected $casts = ['total_bonus' => 'decimal:2', 'total_descontos' => 'decimal:2', 'valor_liquido' => 'decimal:2', 'data_pagamento' => 'date'];
}