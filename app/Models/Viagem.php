<?php
// app/Models/Viagem.php

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
    
    // ✅ ADICIONAR ESTE RELACIONAMENTO AQUI
    /**
     * Relacionamento com Bónus
     */
    public function bonus()
    {
        return $this->hasOne(Bonus::class, 'viagem_id');
    }
    
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
     * Relacionamento com Trela
     */
    public function trela()
    {
        return $this->belongsTo(Trela::class, 'trailer_number', 'matricula')
            ->where('tenant_id', $this->tenant_id);
    }
    
    /**
     * Relacionamento com Motorista
     */
    public function motorista()
    {
        return $this->belongsTo(Motorista::class, 'driver', 'nome_completo')
            ->where('tenant_id', $this->tenant_id);
    }
    
    /**
     * Método de conveniência - alias para motorista()
     */
    public function motoristaRel()
    {
        return $this->motorista();
    }
    
    /**
     * Relacionamento com Container
     */
    public function container()
    {
        return $this->hasOne(Container::class, 'viagem_id')
            ->orWhere(function ($query) {
                $query->where('numero_container', $this->container_no);
            })
            ->where('tenant_id', $this->tenant_id);
    }
    
    /**
     * Relacionamento com Break Bulk Items
     */
    public function breakBulkItems()
    {
        return $this->belongsToMany(BreakBulkItem::class, 'viagem_break_bulk', 'viagem_id', 'break_bulk_item_id')
                    ->withPivot('peso_utilizado', 'quantidade_utilizada')
                    ->withTimestamps()
                    ->where('tenant_id', $this->tenant_id);
    }
    
    /**
     * Relacionamento direto com Break Bulk Item
     */
    public function breakBulkItem()
    {
        return $this->hasOne(BreakBulkItem::class, 'viagem_id')
            ->where('tenant_id', $this->tenant_id);
    }
    
    /**
     * Relacionamento com despesas do motorista
     */
    public function despesas()
    {
        return $this->hasMany(DriverExpense::class, 'viagem_id')
            ->where('tenant_id', $this->tenant_id);
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
     * Verificar se viagem está disponível
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
    
    /**
     * Verificar se viagem pode ser criada com os recursos
     */
    public static function podeCriarViagem($camiaoMatricula, $motoristaNome, $trelaMatricula = null)
    {
        $tenantId = tenant('id');
        
        $camiaoDisponivel = !self::ativas()
            ->where('tenant_id', $tenantId)
            ->where('truck_number', $camiaoMatricula)
            ->exists();
        
        $motoristaDisponivel = !self::ativas()
            ->where('tenant_id', $tenantId)
            ->where('driver', $motoristaNome)
            ->exists();
        
        $trelaDisponivel = true;
        if ($trelaMatricula) {
            $trelaDisponivel = !self::ativas()
                ->where('tenant_id', $tenantId)
                ->where('trailer_number', $trelaMatricula)
                ->exists();
        }
        
        return [
            'camiao_disponivel' => $camiaoDisponivel,
            'motorista_disponivel' => $motoristaDisponivel,
            'trela_disponivel' => $trelaDisponivel,
            'tudo_disponivel' => $camiaoDisponivel && $motoristaDisponivel && $trelaDisponivel
        ];
    }
    
    /**
     * Associar container à viagem
     */
    public function associarContainer($containerId)
    {
        $container = Container::where('tenant_id', $this->tenant_id)
            ->find($containerId);
        
        if ($container) {
            $container->update([
                'viagem_id' => $this->id,
                'status' => 'loaded',
                'is_available' => false,
                'data_carregamento' => now()
            ]);
            
            $this->update([
                'container_no' => $container->numero_container
            ]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Associar break bulk à viagem
     */
    public function associarBreakBulk($breakBulkId, $pesoUtilizado)
    {
        $breakBulkItem = BreakBulkItem::where('tenant_id', $this->tenant_id)
            ->find($breakBulkId);
        
        if ($breakBulkItem) {
            $pesoPorUnidade = $breakBulkItem->peso_por_unidade ?: 50;
            $quantidadeUtilizada = ceil($pesoUtilizado / $pesoPorUnidade);
            
            $breakBulkItem->update([
                'viagem_id' => $this->id,
                'peso_utilizado' => $breakBulkItem->peso_utilizado + $pesoUtilizado,
                'quantidade_utilizada' => $breakBulkItem->quantidade_utilizada + $quantidadeUtilizada,
                'status' => ($breakBulkItem->peso_utilizado >= $breakBulkItem->peso_total) ? 'loaded' : 'partially_used'
            ]);
            
            if (class_exists('App\Models\ViagemBreakBulk')) {
                $this->breakBulkItems()->attach($breakBulkId, [
                    'peso_utilizado' => $pesoUtilizado,
                    'quantidade_utilizada' => $quantidadeUtilizada
                ]);
            }
            
            $this->update([
                'weight' => $pesoUtilizado
            ]);
            
            return true;
        }
        
        return false;
    }
}