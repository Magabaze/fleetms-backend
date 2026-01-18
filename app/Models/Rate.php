<?php
// app/Models/Rate.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Rate extends Model
{
    use BelongsToTenant;
    
    protected $table = 'rates';
    
    protected $fillable = [
        'cliente_id',
        'cliente_nome',
        'distancia_id',
        'distancia_rota',
        'moeda',
        'validade',
        'observacoes',
        'status',
        'criado_por',
        'aprovado_por',
        'itens_carga',
        'tenant_id',
    ];
    
    protected $casts = [
        'validade' => 'date',
        'itens_carga' => 'array',
    ];
    
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
    
    public function distancia()
    {
        return $this->belongsTo(Distancia::class, 'distancia_id');
    }
}