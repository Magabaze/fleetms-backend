<?php
// app/Models/Empresa.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use HasFactory;

    protected $table = 'empresas';

    protected $fillable = [
        'nome',
        'cnpj',
        'email',
        'telefone',
        'website',
        'endereco',
        'cidade',
        'estado',
        'cep',
        'setor',
        'funcionarios',
        'descricao',
        'fundacao',
        'missao',
        'visao',
        'moeda_padrao',
        'fuso_horario',
        'tenant_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relacionamento com tenant
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}