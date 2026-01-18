<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class BreakBulkItem extends Model
{
    use BelongsToTenant;
    
    protected $table = 'break_bulk_items';
    
    protected $fillable = [
        'ordem_id',
        'tipo_embalagem',
        'descricao_embalagem',
        'quantidade',
        'unidades_embalagem',
        'peso_por_unidade',
        'peso_total',
        'peso_utilizado',
        'quantidade_utilizada',
        'dimensoes',
        'volume_por_unidade',
        'volume_total',
        'empilhavel',
        'classe_perigosa',
        'numero_onu',
        'instrucoes_manuseio',
        'status',
        'viagem_id',
        'tenant_id',
    ];
    
    protected $casts = [
        'peso_por_unidade' => 'float',
        'peso_total' => 'float',
        'peso_utilizado' => 'float',
        'quantidade' => 'integer',
        'quantidade_utilizada' => 'integer',
        'volume_por_unidade' => 'float',
        'volume_total' => 'float',
        'empilhavel' => 'boolean',
    ];
    
    public function ordem()
    {
        return $this->belongsTo(Ordem::class, 'ordem_id');
    }
    
    public function viagem()
    {
        return $this->belongsTo(Viagem::class, 'viagem_id');
    }
}