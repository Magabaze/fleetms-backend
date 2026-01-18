<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoEmbalagem extends Model
{
    protected $table = 'tipo_embalagems';

    protected $fillable = [
        'nome',
        'descricao',
        'peso_padrao',
        'unidade',
        'dimensoes_padrao',
        'volume_padrao',
        'empilhavel',
        'max_empilhamento',
        'instrucoes_manuseio',
        'capacidade_maxima',
        'criado_por',
        'tenant_id',
    ];

    protected $casts = [
        'dimensoes_padrao' => 'array',
        'empilhavel' => 'boolean',
    ];
}
