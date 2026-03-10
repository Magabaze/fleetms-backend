<?php
// app/Models/Combustivel/FornecedorCombustivel.php

namespace App\Models\Combustivel;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class FornecedorCombustivel extends Model
{
    use BelongsToTenant;
    
    protected $table = 'fornecedores_combustivel';
    
    protected $fillable = [
        'nome',
        'nif',
        'email',
        'telefone',
        'endereco',
        'status',
        'tenant_id',
    ];
    
    public function pedidos()
    {
        return $this->hasMany(PedidoCompra::class, 'fornecedor_id');
    }
    
    public function postos()
    {
        return $this->hasMany(PostoCombustivel::class, 'fornecedor_id');
    }
}