<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Company extends Model implements TenantWithDatabase
{
    use HasFactory, HasDatabase, HasDomains, CentralConnection;
    
    protected $table = 'tenants';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];
    protected $casts = ['data' => 'array'];
    
    // Métodos obrigatórios do Tenant
    public function run(callable $callback)
    {
        return $callback($this);
    }
    
    public function getTenantKeyName(): string { return 'id'; }
    public function getTenantKey() { return $this->getAttribute($this->getTenantKeyName()); }
    public function getInternal(string $key) { return $this->getAttribute($key); }
    public function setInternal(string $key, $value) { $this->setAttribute($key, $value); return $this; }
    public function getCentralModel() { return static::class; }
    
    // Acessores para os dados da empresa
    public function getNomeAttribute() { return $this->data['nome'] ?? null; }
    public function setNomeAttribute($value) { 
        $data = $this->data ?? []; 
        $data['nome'] = $value; 
        $this->data = $data; 
    }
    
    public function getNomeEmpresaAttribute() { return $this->data['nome'] ?? null; }
    
    public function getCnpjAttribute() { return $this->data['cnpj'] ?? null; }
    public function setCnpjAttribute($value) { 
        $data = $this->data ?? []; 
        $data['cnpj'] = $value; 
        $this->data = $data; 
    }
    
    public function getEmailAttribute() { return $this->data['email'] ?? null; }
    public function setEmailAttribute($value) { 
        $data = $this->data ?? []; 
        $data['email'] = $value; 
        $this->data = $data; 
    }
    
    public function getTelefoneAttribute() { return $this->data['telefone'] ?? null; }
    public function setTelefoneAttribute($value) { 
        $data = $this->data ?? []; 
        $data['telefone'] = $value; 
        $this->data = $data; 
    }
    
    public function getEnderecoAttribute() { return $this->data['endereco'] ?? null; }
    public function setEnderecoAttribute($value) { 
        $data = $this->data ?? []; 
        $data['endereco'] = $value; 
        $this->data = $data; 
    }
    
    public function getCidadeAttribute() { return $this->data['cidade'] ?? null; }
    public function setCidadeAttribute($value) { 
        $data = $this->data ?? []; 
        $data['cidade'] = $value; 
        $this->data = $data; 
    }
    
    public function getEstadoAttribute() { return $this->data['estado'] ?? null; }
    public function setEstadoAttribute($value) { 
        $data = $this->data ?? []; 
        $data['estado'] = $value; 
        $this->data = $data; 
    }
    
    public function getCepAttribute() { return $this->data['cep'] ?? null; }
    public function setCepAttribute($value) { 
        $data = $this->data ?? []; 
        $data['cep'] = $value; 
        $this->data = $data; 
    }
    
    public function getSetorAttribute() { return $this->data['setor'] ?? null; }
    public function setSetorAttribute($value) { 
        $data = $this->data ?? []; 
        $data['setor'] = $value; 
        $this->data = $data; 
    }
    
    public function getDescricaoAttribute() { return $this->data['descricao'] ?? null; }
    public function setDescricaoAttribute($value) { 
        $data = $this->data ?? []; 
        $data['descricao'] = $value; 
        $this->data = $data; 
    }
    
    // Método para criar empresa com domínio
    public static function createWithDomain(array $attributes, string $domain): self
    {
        $company = self::create([
            'id' => $attributes['id'] ?? \Illuminate\Support\Str::uuid(),
            'data' => [
                'nome' => $attributes['nome'],
                'cnpj' => $attributes['cnpj'] ?? null,
                'email' => $attributes['email'] ?? null,
                'telefone' => $attributes['telefone'] ?? null,
                'endereco' => $attributes['endereco'] ?? null,
                'cidade' => $attributes['cidade'] ?? null,
                'estado' => $attributes['estado'] ?? null,
                'cep' => $attributes['cep'] ?? null,
                'setor' => $attributes['setor'] ?? null,
                'descricao' => $attributes['descricao'] ?? null,
            ],
        ]);
        
        $company->domains()->create(['domain' => $domain]);
        return $company;
    }
    
    // Relacionamento com EmpresaCodigo (código prefixo)
    public function codigo()
    {
        return $this->hasOne(EmpresaCodigo::class, 'tenant_id', 'id')
            ->where('is_active', true);
    }
    
    // Método para obter código prefixo
    public function getCodigoPrefixAttribute()
    {
        return $this->codigo ? $this->codigo->codigo_prefixo : null;
    }
}