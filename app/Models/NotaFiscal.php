<?php
// app/Models/NotaFiscal.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotaFiscal extends Model
{
    use HasFactory;

    protected $table = 'notas_fiscais';

    protected $fillable = [
        'numero',
        'tipo',
        'cliente_id',
        'cliente_nome',  // Nome correto do campo
        'ordem_id',
        'valor',
        'motivo',
        'data',
        'fatura_referencia',
        'observacoes',
        'criado_por',
        'tenant_id'
    ];

    protected $casts = [
        'data' => 'date',
        'valor' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function ordem()
    {
        return $this->belongsTo(OrdemFaturacao::class, 'ordem_id');
    }

    // Acessor para compatibilidade
    public function getClienteAttribute()
    {
        return $this->cliente_nome;
    }
}