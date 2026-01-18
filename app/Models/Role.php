<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Role extends Model
{
    use BelongsToTenant;
    
    protected $table = 'roles';
    
    protected $fillable = [
        'nome',
        'descricao',
        'is_super_admin',
        'tenant_id'
    ];
    
    public function users()
    {
        return $this->hasMany(User::class);
    }
    
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }
    
    public function hasPermission($permissionKey)
    {
        if ($this->is_super_admin) {
            return true;
        }
        
        return $this->permissions()->where('chave', $permissionKey)->exists();
    }
    
    public function getPermissionKeys()
    {
        return $this->permissions()->pluck('chave')->toArray();
    }
}