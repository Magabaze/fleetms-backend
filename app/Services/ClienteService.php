<?php
// app/Services/ClienteService.php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ClienteService
{
    public function listarClientes(array $filtros = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Cliente::query();
        
        // Busca global
        if (!empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nome_empresa', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('telefone', 'like', "%{$search}%")
                  ->orWhere('nuit_nif', 'like', "%{$search}%")
                  ->orWhere('pessoa_contato', 'like', "%{$search}%");
            });
        }
        
        // Filtro por tipo
        if (!empty($filtros['tipo_cliente']) && $filtros['tipo_cliente'] !== 'todos') {
            $query->where('tipo_cliente', $filtros['tipo_cliente']);
        }
        
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }
    
    public function buscarCliente(int $id): ?Cliente
    {
        return Cliente::find($id);
    }
    
    public function criarCliente(array $dados): Cliente
    {
        // Validar NUIT único
        $existe = Cliente::where('nuit_nif', $dados['nuit_nif'])->exists();
        if ($existe) {
            throw new \Exception('Já existe um cliente com este NUIT/NIF');
        }
        
        return Cliente::create($dados);
    }
    
    public function atualizarCliente(int $id, array $dados): Cliente
    {
        $cliente = $this->buscarCliente($id);
        
        if (!$cliente) {
            throw new \Exception('Cliente não encontrado');
        }
        
        // Se mudou o NUIT, validar único
        if (isset($dados['nuit_nif']) && $dados['nuit_nif'] !== $cliente->nuit_nif) {
            $existe = Cliente::where('nuit_nif', $dados['nuit_nif'])
                           ->where('id', '!=', $id)
                           ->exists();
            if ($existe) {
                throw new \Exception('Já existe outro cliente com este NUIT/NIF');
            }
        }
        
        $cliente->update($dados);
        return $cliente->fresh();
    }
    
    public function excluirCliente(int $id): bool
    {
        $cliente = $this->buscarCliente($id);
        
        if (!$cliente) {
            throw new \Exception('Cliente não encontrado');
        }
        
        return $cliente->delete();
    }
    
    public function estatisticas(): array
    {
        $total = Cliente::count();
        
        $porTipo = Cliente::selectRaw('tipo_cliente, count(*) as total')
            ->groupBy('tipo_cliente')
            ->get()
            ->pluck('total', 'tipo_cliente')
            ->toArray();
        
        $ultimosMeses = Cliente::where('created_at', '>=', now()->subMonths(3))
            ->selectRaw('MONTH(created_at) as mes, COUNT(*) as total')
            ->groupBy('mes')
            ->get()
            ->pluck('total', 'mes')
            ->toArray();
        
        return [
            'total' => $total,
            'por_tipo' => $porTipo,
            'ultimos_meses' => $ultimosMeses,
        ];
    }
}