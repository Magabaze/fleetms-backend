<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Camiao extends Model
{
    use BelongsToTenant;
    
    protected $table = 'camioes';
    
    protected $fillable = [
        'matricula',
        'marca',
        'modelo',
        'ano_fabricacao',
        'capacidade_carga',
        'tipo_combustivel',
        'consumo_medio',
        'numero_eixos',
        'tara',
        'cmr',
        'seguro_validade',
        'inspecao_validade',
        'estado',
        'localizacao',
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