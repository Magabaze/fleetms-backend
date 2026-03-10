<?php
// app/Models/Bonus.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bonus extends Model
{
    use HasFactory;

    protected $table = 'bonus';

    protected $fillable = [
        'viagem_id',
        'regra_bonus_id',
        'motorista',
        'descricao',
        'valor',
        'status',
        'tenant_id'
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function viagem()
    {
        return $this->belongsTo(Viagem::class);
    }

    public function regra()
    {
        return $this->belongsTo(RegraBonus::class, 'regra_bonus_id');
    }
}