<?php
// app/Models/Carga.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Carga extends Model
{
    use BelongsToTenant;
    
    protected $table = 'cargas';
    
    protected $fillable = [
        'tipo_carga',
        'descricao',
        'valor',
        'peso',
        'volume',
        'dimensoes',
        'observacoes',
        'criado_por',
        'tenant_id',
    ];
    
    protected $casts = [
        'valor' => 'decimal:2',
    ];
}