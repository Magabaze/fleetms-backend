<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Container extends Model
{
    use BelongsToTenant;
    
    protected $table = 'containers';
    
    protected $fillable = [
        'ordem_id',
        'tipo_recipiente',
        'tipo_carga',
        'unidade',
        'peso_liquido',
        'peso_container',
        'peso_total',
        'numero_container',
        'selo',
        'aterramento_ref',
        'data_validade_do',
        'drop_off_details',
        'deposito_contentores',
        'status',
        'is_available',
        'viagem_id',
        'localizacao_atual',
        'data_carregamento',
        'data_descarga',
        'tenant_id',
    ];
    
    protected $casts = [
        'peso_liquido' => 'float',
        'peso_container' => 'float',
        'peso_total' => 'float',
        'is_available' => 'boolean',
        'data_validade_do' => 'date',
        'data_carregamento' => 'datetime',
        'data_descarga' => 'datetime',
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