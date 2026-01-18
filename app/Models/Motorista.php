<?php
// app/Models/Motorista.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Motorista extends Model
{
    use BelongsToTenant;
    
    protected $table = 'motoristas';
    
    protected $fillable = [
        'nome_completo',
        'numero_carta',
        'numero_passaporte',
        'nacionalidade',
        'telefone',
        'telefone_alternativo',
        'email',
        'endereco',
        'tipo_licenca',
        'validade_licenca',
        'validade_passaporte',
        'status',
        'observacoes',
        'foto_url',
        'foto_carta_url',
        'foto_passaporte_url',
        'documentos',
        'criado_por',
        'tenant_id',
    ];
    
    protected $casts = [
        'validade_licenca' => 'date',
        'validade_passaporte' => 'date',
        'documentos' => 'array',
    ];
}