<?php
// app/Models/Combustivel/AbastecimentoExterno.php

namespace App\Models\Combustivel;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

// ✅ IMPORTA O MODELO REAL DO VEÍCULO (Camiao)
use App\Models\Camiao;
// ✅ SE O MODELO DO MOTORISTA TAMBÉM ESTIVER EM App\Models, ADICIONA:
// use App\Models\Motorista;

class AbastecimentoExterno extends Model
{
    use BelongsToTenant;
    
    protected $table = 'abastecimentos_externos';
    
    protected $fillable = [
        'numero',
        'veiculo_id',
        'motorista_id',
        'posto_id',
        'tipo_combustivel',
        'quantidade',
        'preco_unitario',
        'valor_total',
        'moeda',
        'odometro',
        'data_abastecimento', 
        'nota_fiscal',
        'status',
        'observacoes',
        'responsavel_registro',
        'aprovado_por',
        'data_aprovacao',
        'pago_por',
        'data_pagamento',
        'tenant_id',
        'viagem_id',
        'numero_viagem',
        'distancia_percorrida',
        'veiculo_matricula',
        'motorista_nome',
    ];
    
    protected $casts = [
        'data_abastecimento' => 'date',
        'data_aprovacao' => 'datetime',
        'data_pagamento' => 'datetime',
        'quantidade' => 'decimal:2',
        'preco_unitario' => 'decimal:2',
        'valor_total' => 'decimal:2',
        'odometro' => 'integer',
        'distancia_percorrida' => 'decimal:2',
    ];
    
    public function veiculo()
    {
        // ✅ ALTERADO: Agora aponta para o modelo Camiao
        // O segundo parâmetro 'veiculo_id' mantém-se porque é o nome da coluna na tabela 'abastecimentos_externos'
        return $this->belongsTo(Camiao::class, 'veiculo_id');
    }
    
    public function motorista()
    {
        // ✅ ALTERADO: Aponta para App\Models\Motorista
        // Se der erro de "Class not found", verifica se o ficheiro existe em app/Models/Motorista.php
        return $this->belongsTo(\App\Models\Motorista::class, 'motorista_id');
    }
    
    public function posto()
    {
        // PostoCombustivel funciona porque está no mesmo namespace
        return $this->belongsTo(PostoCombustivel::class, 'posto_id');
    }
}