<?php
// app/Models/Ordem.php - VERSÃO CORRIGIDA

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Ordem extends Model
{
    use BelongsToTenant;
    
    protected $table = 'ordens';
    
    // LISTA COMPLETA DOS CAMPOS QUE PODEM SER PREENCHIDOS
    protected $fillable = [
        'order_numero',
        'tipo_transito',
        'cliente_id',
        'consignee_id',
        'expedidor_id',
        'origem',
        'destino',
        'commodity',
        'tipo_carga',
        'status',
        'created_date',
        'previsao_carregamento',
        'numero_bl',
        'shipping_line',
        'fronteira',
        'agente_fronteira',
        'taxa_cliente_id',
        'moeda_fatura',
        'peso_total',
        'volume_total',
        'observacoes',
        'criado_por',
        'aprovado_por',
        'empresa',  // ADICIONAR ESTE CAMPO
        'tenant_id',
    ];
    
    protected $casts = [
        'status' => 'string',
        'created_date' => 'date',
        'previsao_carregamento' => 'date',
    ];
    
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
    
    public function consignee()
    {
        return $this->belongsTo(Cliente::class, 'consignee_id');
    }
    
    public function expedidor()
    {
        return $this->belongsTo(Cliente::class, 'expedidor_id');
    }
    
    public function containers()
    {
        return $this->hasMany(Container::class, 'ordem_id');
    }
    
    public function breakBulkItems()
    {
        return $this->hasMany(BreakBulkItem::class, 'ordem_id');
    }
}