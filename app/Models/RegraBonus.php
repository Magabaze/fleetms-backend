<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class RegraBonus extends Model
{
    use BelongsToTenant;
    protected $table = 'regras_bonus';
    protected $fillable = ['nome', 'transit_type', 'load_status', 'cargo_nature', 'calculation_base', 'valor_bonus', 'status', 'criado_por', 'tenant_id'];
    protected $casts = ['valor_bonus' => 'decimal:2'];
    
    public function toArray() {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'transitType' => $this->transit_type,
            'loadStatus' => $this->load_status,
            'cargoNature' => $this->cargo_nature,
            'calculationBase' => $this->calculation_base,
            'valorBonus' => (float) $this->valor_bonus,
            'status' => $this->status,
            'criadoPor' => $this->criado_por,
            'createdAt' => $this->created_at->toISOString(),
            'updatedAt' => $this->updated_at->toISOString(),
        ];
    }
}