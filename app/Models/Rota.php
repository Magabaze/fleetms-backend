<?php
// app/Models/Rota.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Rota extends Model
{
    use BelongsToTenant;
    
    protected $table = 'rotas';
    
    protected $fillable = [
        'rota',
        'origem',
        'destino',
        'distancia',
        'criado_por',
        'tenant_id',
    ];
    
    protected $casts = [
        'distancia' => 'float',
    ];
    
    public function despesas()
    {
        return $this->hasMany(Despesa::class, 'rota_id');
    }
}