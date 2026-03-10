<?php

namespace App\Models\Manutencao;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Socorro extends Model
{
    use BelongsToTenant;
    
    protected $table = 'socorros';
    
    protected $fillable = [
        'codigo',
        'ordem_id',
        'ordem_codigo',
        'veiculo',
        'matricula',
        'motorista',
        'tipo',
        'descricao',
        'status',
        'prioridade',
        'data_ocorrencia',
        'local',
        'km',
        'tecnico_enviado',
        'tempo_resposta',
        'tempo_reparo',
        'custo',
        'observacoes',
        'tenant_id',
        'criado_por',
    ];
    
    protected $casts = [
        'data_ocorrencia' => 'datetime',
        'km' => 'integer',
        'tempo_resposta' => 'integer',
        'tempo_reparo' => 'integer',
        'custo' => 'decimal:2',
    ];
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($socorro) {
            if (!$socorro->codigo) {
                $lastId = static::max('id') ?? 0;
                $socorro->codigo = 'SOC-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);
            }
        });
    }
    
    public function ordem()
    {
        return $this->belongsTo(OrdemTrabalho::class, 'ordem_id');
    }
}