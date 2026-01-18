<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Cliente extends Model
{
    use BelongsToTenant;
    
    protected $table = 'clientes';
    
    protected $fillable = [
        'nome_empresa',
        'tipo_cliente',
        'pessoa_contato',
        'telefone',
        'email',
        'endereco',
        'nuit_nif',
        'iva',
        'pais',
        'observacoes',
        'criado_por',
        'tenant_id',
    ];
    
    protected $casts = [
        'tipo_cliente' => 'string',
    ];
    
    // Se quiser acessar como camelCase
    public function getNomeEmpresaAttribute($value)
    {
        return $value;
    }
    
    public function setNomeEmpresaAttribute($value)
    {
        $this->attributes['nome_empresa'] = $value;
    }
    
    // ... outros getters/setters se necessário
}