<?php
// app/Models/Combustivel/PedidoCompra.php

namespace App\Models\Combustivel;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class PedidoCompra extends Model
{
    use BelongsToTenant;
    
    protected $table = 'pedidos_compra_combustivel';
    
    protected $fillable = [
        'numero',
        'fornecedor',
        'tipo_combustivel',
        'quantidade',
        'unidade_medida',
        'preco_unitario',
        'valor_total',
        'moeda',
        'data_pedido',
        'data_entrega_prevista',
        'data_entrega_real',
        'status',
        'observacoes',
        'criado_por',
        'aprovado_por',
        'data_aprovacao',
        'tenant_id',
    ];
    
    protected $casts = [
        'data_pedido' => 'datetime',
        'data_entrega_prevista' => 'datetime',
        'data_entrega_real' => 'datetime',
        'data_aprovacao' => 'datetime',
        'quantidade' => 'decimal:2',
        'preco_unitario' => 'decimal:2',
        'valor_total' => 'decimal:2',
    ];
    
    /**
     * Status disponíveis para o pedido
     */
    const STATUS = [
        'pendente' => 'Pendente',
        'aprovado' => 'Aprovado',
        'rejeitado' => 'Rejeitado',
        'entregue' => 'Entregue',
        'cancelado' => 'Cancelado',
    ];
    
    /**
     * Unidades de medida disponíveis
     */
    const UNIDADES_MEDIDA = [
        'litros' => 'Litros',
        'galoes' => 'Galões',
        'metros_cubicos' => 'Metros Cúbicos',
    ];
    
    /**
     * Moedas disponíveis
     */
    const MOEDAS = [
        'EUR' => 'Euro',
        'USD' => 'Dólar',
        'MZN' => 'Metical',
        'ZAR' => 'Rand',
        'BRL' => 'Real',
    ];
    
    /**
     * Relacionamento com Tanque
     */
    public function tanque()
    {
        return $this->belongsTo(Tanque::class, 'tanque_id');
    }
    
    /**
     * Accessor para label do status
     */
    public function getStatusLabelAttribute()
    {
        return self::STATUS[$this->status] ?? $this->status;
    }
    
    /**
     * Accessor para unidade de medida formatada
     */
    public function getUnidadeMedidaLabelAttribute()
    {
        return self::UNIDADES_MEDIDA[$this->unidade_medida] ?? $this->unidade_medida;
    }
    
    /**
     * Accessor para moeda formatada
     */
    public function getMoedaLabelAttribute()
    {
        return self::MOEDAS[$this->moeda] ?? $this->moeda;
    }
    
    /**
     * Scope para pedidos pendentes
     */
    public function scopePendentes($query)
    {
        return $query->where('status', 'pendente');
    }
    
    /**
     * Scope para pedidos aprovados
     */
    public function scopeAprovados($query)
    {
        return $query->where('status', 'aprovado');
    }
    
    /**
     * Scope para pedidos entregues
     */
    public function scopeEntregues($query)
    {
        return $query->where('status', 'entregue');
    }
    
    /**
     * Scope para pedidos do período
     */
    public function scopeNoPeriodo($query, $inicio, $fim)
    {
        return $query->whereBetween('data_pedido', [$inicio, $fim]);
    }
    
    /**
     * Scope para pedidos por fornecedor
     */
    public function scopePorFornecedor($query, $fornecedor)
    {
        return $query->where('fornecedor', 'like', "%{$fornecedor}%");
    }
    
    /**
     * Verificar se o pedido pode ser editado
     */
    public function podeEditar()
    {
        return $this->status === 'pendente';
    }
    
    /**
     * Verificar se o pedido pode ser aprovado
     */
    public function podeAprovar()
    {
        return $this->status === 'pendente';
    }
    
    /**
     * Verificar se o pedido pode ser rejeitado
     */
    public function podeRejeitar()
    {
        return $this->status === 'pendente';
    }
    
    /**
     * Verificar se o pedido pode ser entregue
     */
    public function podeEntregar()
    {
        return $this->status === 'aprovado';
    }
    
    /**
     * Verificar se o pedido pode ser cancelado
     */
    public function podeCancelar()
    {
        return in_array($this->status, ['pendente', 'aprovado']);
    }
    
    /**
     * Verificar se o pedido pode ser excluído
     */
    public function podeExcluir()
    {
        return $this->status === 'pendente';
    }
}