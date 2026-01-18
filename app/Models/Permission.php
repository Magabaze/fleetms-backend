<?php
// app/Models/Permission.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Permission extends Model
{
    use BelongsToTenant;
    
    protected $table = 'permissions';
    
    protected $fillable = [
        'nome',
        'chave',
        'modulo',
        'descricao',
        'tenant_id'
    ];
    
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }
}