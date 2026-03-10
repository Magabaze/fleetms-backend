<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Desconto extends Model
{
    use BelongsToTenant;
    protected $table = 'descontos';
    protected $fillable = ['motorista', 'tipo', 'descricao', 'valor', 'data_desconto', 'status', 'criado_por', 'tenant_id'];
    protected $casts = ['valor' => 'decimal:2', 'data_desconto' => 'date'];
    
    public function toArray() {
        return [
            'id' => $this->id,
            'motorista' => $this->motorista,
            'tipo' => $this->tipo,
            'descricao' => $this->descricao,
            'valor' => (float) $this->valor,
            'data' => $this->data_desconto->toISOString(),
            'status' => $this->status,
            'criadoPor' => $this->criado_por,
        ];
    }
}