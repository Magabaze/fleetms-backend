<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Viagem extends Model
{
    use BelongsToTenant;
    
    protected $table = 'viagens';
    
    protected $fillable = [
        'trip_number',
        'trip_slno',
        'order_number',
        'customer_name',
        'from_station',
        'to_station',
        'truck_number',
        'trailer_number',
        'driver',
        'container_no',
        'bl_number',
        'commodity',
        'cargo_type',
        'weight',
        'status',
        'current_status',
        'schedule_date',
        'delivery_date',
        'actual_delivery',
        'pod_delivery_date',
        'current_position',
        'tracking_comments',
        'border_arrival_date',
        'border_demurrage_days',
        'offloading_arrival_date',
        'offloading_demurrage_days',
        'is_empty_trip',
        'is_company_owned',
        'is_ready_for_invoice',
        'invoice_number',
        'transporter',
        'order_owner',
        'created_by',
        'tenant_id',
    ];
    
    protected $casts = [
        'schedule_date' => 'date',
        'delivery_date' => 'date',
        'actual_delivery' => 'date',
        'pod_delivery_date' => 'date',
        'border_arrival_date' => 'date',
        'offloading_arrival_date' => 'date',
        'is_empty_trip' => 'boolean',
        'is_company_owned' => 'boolean',
        'is_ready_for_invoice' => 'boolean',
        'weight' => 'decimal:2',
        'border_demurrage_days' => 'integer',
        'offloading_demurrage_days' => 'integer',
    ];
    
    // Geração automática do trip number
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($viagem) {
            // Se não tiver trip number, gerar um
            if (!$viagem->trip_number) {
                $year = date('Y');
                $lastTrip = self::where('tenant_id', $viagem->tenant_id)
                    ->whereYear('created_at', $year)
                    ->orderBy('id', 'desc')
                    ->first();
                
                $nextNumber = $lastTrip ? 
                    intval(substr($lastTrip->trip_number, -4)) + 1 : 1;
                
                $viagem->trip_number = sprintf('TRIP-%s-%04d', $year, $nextNumber);
                $viagem->trip_slno = '1';
            }
        });
    }
    
    // 🔴 RELACIONAMENTOS ATUALIZADOS:
    
    /**
     * Relacionamento com Ordem
     */
    public function ordem()
    {
        return $this->belongsTo(Ordem::class, 'order_number', 'order_numero')
            ->where('tenant_id', $this->tenant_id);
    }
    
    /**
     * Relacionamento com Camiao
     */
    public function camiao()
    {
        return $this->belongsTo(Camiao::class, 'truck_number', 'matricula')
            ->where('tenant_id', $this->tenant_id);
    }
    
    /**
     * 🔴 RELACIONAMENTO NOVO com Trela
     */
    public function trela()
    {
        return $this->belongsTo(Trela::class, 'trailer_number', 'matricula')
            ->where('tenant_id', $this->tenant_id);
    }
    
    /**
     * 🔴 RELACIONAMENTO ATUALIZADO com Motorista
     * Usando nome_completo como chave
     */
    public function motorista()
    {
        return $this->belongsTo(Motorista::class, 'driver', 'nome_completo')
            ->where('tenant_id', $this->tenant_id);
    }
    
    /**
     * Método de conveniência - alias para motorista()
     * (mantém compatibilidade com código existente)
     */
    public function motoristaRel()
    {
        return $this->motorista();
    }
    
    /**
     * Escopo para viagens ativas (não fechadas)
     */
    public function scopeAtivas($query)
    {
        return $query->where('status', '!=', 'Closed');
    }
    
    /**
     * Escopo para viagens do tenant atual
     */
    public function scopeDoTenant($query)
    {
        return $query->where('tenant_id', tenant('id'));
    }
    
    /**
     * Verificar se viagem está disponível (não tem viagens ativas)
     */
    public static function verificarDisponibilidade($tipo, $valor, $excluirId = null)
    {
        $query = self::ativas()->where('tenant_id', tenant('id'));
        
        switch ($tipo) {
            case 'motorista':
                $query->where('driver', $valor);
                break;
            case 'camiao':
                $query->where('truck_number', $valor);
                break;
            case 'trela':
                $query->where('trailer_number', $valor);
                break;
        }
        
        if ($excluirId) {
            $query->where('id', '!=', $excluirId);
        }
        
        return $query->doesntExist();
    }
}