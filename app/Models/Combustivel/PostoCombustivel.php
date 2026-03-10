<?php
// app/Models/Combustivel/PostoCombustivel.php

namespace App\Models\Combustivel;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class PostoCombustivel extends Model
{
    use BelongsToTenant;
    
    protected $table = 'postos_combustivel';
    
    protected $fillable = [
        'nome',
        'localizacao',
        'fornecedor_id',
        'status',
        'tenant_id',
    ];
    
    public function fornecedor()
    {
        return $this->belongsTo(FornecedorCombustivel::class, 'fornecedor_id');
    }
    
    public function abastecimentos()
    {
        return $this->hasMany(AbastecimentoExterno::class, 'posto_id');
    }
}