<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use HasFactory;

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
        'logo_url', // ✅ Obrigatório
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Correção do relacionamento usando caminho absoluto
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}