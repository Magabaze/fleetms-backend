<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    
    public function rules(): array
    {
        return [
            'nomeEmpresa' => 'required|string|max:255',
            'tipoCliente' => 'required|in:Consignee,Shipper,Invoice Party',
            'pessoaContato' => 'required|string|max:255',
            'telefone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'endereco' => 'required|string',
            'nuitNif' => 'required|string|size:9',
            'iva' => 'nullable|string|max:20',
            'pais' => 'required|string|max:100',
            'observacoes' => 'nullable|string',
            'criadoPor' => 'required|string|max:255',
        ];
    }
    
    public function messages(): array
    {
        return [
            'nomeEmpresa.required' => 'Nome da empresa é obrigatório',
            'tipoCliente.required' => 'Tipo de cliente é obrigatório',
            'nuitNif.required' => 'NUIT/NIF é obrigatório',
            'nuitNif.size' => 'NUIT/NIF deve ter 9 dígitos',
            'email.required' => 'Email é obrigatório',
            'email.email' => 'Email inválido',
        ];
    }
}