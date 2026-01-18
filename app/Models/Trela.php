<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Trela extends Model
{
    use BelongsToTenant;
    
    protected $table = 'trelas';
    
    protected $fillable = [
        'matricula',
        'marca',
        'modelo',
        'ano_fabricacao',
        'tipo_trela',
        'capacidade_carga',
        'numero_eixos',
        'tara',
        'cmr',
        'seguro_validade',
        'inspecao_validade',
        'estado',
        'camiao_associado',
        'observacoes',
        'criado_por',
        'tenant_id',
    ];
    
    protected $casts = [
        'ano_fabricacao' => 'integer',
        'numero_eixos' => 'integer',
        'seguro_validade' => 'date',
        'inspecao_validade' => 'date',
    ];
}