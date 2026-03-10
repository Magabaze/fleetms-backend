<?php

namespace App\Models\Manutencao;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Avaria extends Model
{
    use BelongsToTenant;
    
    protected $table = 'avarias';
    
    protected $fillable = [
        'codigo',
        'veiculo',
        'matricula',
        'descricao',
        'causa_raiz',
        'reportado_por',
        'tecnico',
        'status',
        'prioridade',
        'data_reporte',
        'horas_imobilizado',
        'local_avaria',
        'tenant_id',
        'criado_por',
    ];
    
    protected $casts = [
        'data_reporte' => 'date',
    ];
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($avaria) {
            if (!$avaria->codigo) {
                $lastId = static::max('id') ?? 0;
                $avaria->codigo = 'AV-' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);
            }
        });
    }
}