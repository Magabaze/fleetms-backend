<?php
// app/Models/Agente.php - MODELO CORRIGIDO

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Agente extends Model
{
    use BelongsToTenant;
    
    protected $table = 'agentes';
    
    protected $fillable = [
        'nome_completo',
        'local_atuacao',
        'fronteira_associada',
        'telefone',
        'email',
        'taxa_servico',
        'moeda',
        'documentos',
        'observacoes',
        'status',
        'criado_por',
        'tenant_id',
    ];
    
    protected $casts = [
        'documentos' => 'array',
        'taxa_servico' => 'decimal:2',
    ];
    
    public function getStatusAttribute($value)
    {
        return $value ?? 'ativo';
    }
    
    public function getDocumentosAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }
    
    public function setDocumentosAttribute($value)
    {
        $this->attributes['documentos'] = json_encode($value ?? []);
    }
}