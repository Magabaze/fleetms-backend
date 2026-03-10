<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, BelongsToTenant;

    protected $fillable = [
        'name',
        'email',
        'password',
        'telefone',
        'cargo',
        'departamento',
        'endereco',
        'bio',
        'role_id',
        'user_type', // ADICIONAR ESTA LINHA: transportador, agent, shipper
        'idioma',
        'fuso_horario',
        'tenant_id',
        'ativo',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'ativo' => 'boolean',
    ];
    
    public function role()
    {
        return $this->belongsTo(Role::class);
    }
    
    public function isSuperAdmin()
    {
        return $this->role && $this->role->is_super_admin;
    }
    
    public function hasPermission($permissionKey)
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        return $this->role && $this->role->hasPermission($permissionKey);
    }
}