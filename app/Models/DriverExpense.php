<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverExpense extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'driver_expenses';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'viagem_id',
        'expense_head',
        'amount',
        'currency',
        'driver_name',
        'payment_description',
        'created_by',
        'created_by_id',
        'status',
        'is_active'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'deleted_at'
    ];

    /**
     * Get the viagem that owns the DriverExpense.
     */
    public function viagem(): BelongsTo
    {
        return $this->belongsTo(Viagem::class, 'viagem_id');
    }

    /**
     * Get the user that created the DriverExpense.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Relacionamento com TipoDespesa (pelo expense_head)
     */
    public function tipoDespesa(): BelongsTo
    {
        return $this->belongsTo(TipoDespesa::class, 'expense_head', 'nome');
    }

    /**
     * Accessor para obter o nome do tipo de despesa
     */
    public function getTipoNomeAttribute(): string
    {
        // Se o relacionamento foi carregado e existe
        if ($this->relationLoaded('tipoDespesa') && $this->tipoDespesa) {
            return $this->tipoDespesa->nome;
        }
        
        // Fallback: retorna o valor do expense_head
        return $this->expense_head ?? '—';
    }

    /**
     * Scope a query to only include active expenses.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include pending expenses.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include approved expenses.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope a query to only include paid expenses.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope a query to only include settled expenses.
     */
    public function scopeSettled($query)
    {
        return $query->where('status', 'settled');
    }

    /**
     * Scope a query to only include cancelled expenses.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopePorStatus($query, $status)
    {
        if ($status && in_array($status, ['pending', 'approved', 'paid', 'settled', 'cancelled'])) {
            return $query->where('status', $status);
        }
        return $query;
    }

    /**
     * Scope a query to filter by viagem.
     */
    public function scopePorViagem($query, $viagemId)
    {
        if ($viagemId) {
            return $query->where('viagem_id', $viagemId);
        }
        return $query;
    }

    /**
     * Scope a query to filter by driver name.
     */
    public function scopePorMotorista($query, $driverName)
    {
        if ($driverName) {
            return $query->where('driver_name', 'like', '%' . $driverName . '%');
        }
        return $query;
    }

    /**
     * Scope a query to filter by expense head.
     */
    public function scopePorTipo($query, $expenseHead)
    {
        if ($expenseHead) {
            return $query->where('expense_head', 'like', '%' . $expenseHead . '%');
        }
        return $query;
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopePorPeriodo($query, $startDate = null, $endDate = null)
    {
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }
        
        return $query;
    }

    /**
     * Check if the expense is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the expense is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the expense is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if the expense is settled.
     */
    public function isSettled(): bool
    {
        return $this->status === 'settled';
    }

    /**
     * Check if the expense is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if the expense is active.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Get the formatted amount with currency.
     */
    public function getFormattedAmountAttribute(): string
    {
        return $this->currency . ' ' . number_format($this->amount, 2);
    }

    /**
     * Get the status badge class.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'pending' => 'badge badge-warning',
            'approved' => 'badge badge-success',
            'paid' => 'badge badge-info',
            'settled' => 'badge badge-primary',
            'cancelled' => 'badge badge-danger',
            default => 'badge badge-secondary'
        };
    }

    /**
     * Get the status text.
     */
    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pendente',
            'approved' => 'Aprovada',
            'paid' => 'Paga',
            'settled' => 'Liquidada',
            'cancelled' => 'Cancelada',
            default => 'Desconhecido'
        };
    }

    /**
     * Get the status color.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'approved' => 'green',
            'paid' => 'blue',
            'settled' => 'purple',
            'cancelled' => 'red',
            default => 'gray'
        };
    }

    /**
     * Approve the expense.
     */
    public function approve(): bool
    {
        $this->status = 'approved';
        return $this->save();
    }

    /**
     * Cancel the expense.
     */
    public function cancel(): bool
    {
        $this->status = 'cancelled';
        $this->is_active = false;
        return $this->save();
    }

    /**
     * Mark as paid.
     */
    public function markAsPaid(): bool
    {
        $this->status = 'paid';
        return $this->save();
    }

    /**
     * Mark as settled.
     */
    public function markAsSettled(): bool
    {
        $this->status = 'settled';
        return $this->save();
    }

    /**
     * Soft delete the expense.
     */
    public function softDelete(): bool
    {
        $this->is_active = false;
        return $this->save();
    }

    /**
     * Restore the expense.
     */
    public function restoreExpense(): bool
    {
        $this->is_active = true;
        return $this->save();
    }

    /**
     * Get expense summary data.
     */
    public static function getSummaryForViagem($viagemId): array
    {
        $expenses = self::where('viagem_id', $viagemId)
            ->where('is_active', true)
            ->get();

        $total = $expenses->sum('amount');
        $approved = $expenses->where('status', 'approved')->sum('amount');
        $pending = $expenses->where('status', 'pending')->sum('amount');
        $cancelled = $expenses->where('status', 'cancelled')->sum('amount');

        return [
            'total' => $total,
            'approved' => $approved,
            'pending' => $pending,
            'cancelled' => $cancelled,
            'total_count' => $expenses->count(),
            'approved_count' => $expenses->where('status', 'approved')->count(),
            'pending_count' => $expenses->where('status', 'pending')->count(),
            'cancelled_count' => $expenses->where('status', 'cancelled')->count(),
            'currency' => $expenses->first()->currency ?? 'USD'
        ];
    }

    /**
     * Get available expense heads (types) from existing data.
     */
    public static function getAvailableExpenseHeads(): array
    {
        $heads = self::select('expense_head')
            ->distinct()
            ->whereNotNull('expense_head')
            ->where('expense_head', '!=', '')
            ->orderBy('expense_head')
            ->pluck('expense_head')
            ->toArray();

        // Add default heads if none exist
        if (empty($heads)) {
            $heads = ['Fuel', 'Tolls', 'Parking', 'Accommodation', 'Food', 'Repairs', 'Other'];
        }

        return $heads;
    }

    /**
     * Get expenses grouped by type.
     */
    public static function getExpensesByType($viagemId): array
    {
        return self::where('viagem_id', $viagemId)
            ->where('is_active', true)
            ->selectRaw('expense_head, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('expense_head')
            ->orderBy('total', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->expense_head,
                    'total' => (float) $item->total,
                    'count' => (int) $item->count
                ];
            })
            ->toArray();
    }
}