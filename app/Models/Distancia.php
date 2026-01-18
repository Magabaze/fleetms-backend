<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Distancia extends Model
{
    use BelongsToTenant;
    
    protected $table = 'distancias';
    
    protected $fillable = [
        'origem',
        'destino',
        'distancia_total',
        'tempo_estimado',
        'pontos_parada',
        'estrada_preferencial',
        'observacoes',
        'criado_por',
        'tenant_id',
    ];
}