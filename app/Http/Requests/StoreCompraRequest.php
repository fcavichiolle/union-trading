<?php

namespace App\Http\Requests;

use App\Models\Compra;
use App\Rules\CnpjValido;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin', 'compras') ?? false;
    }

    public function rules(): array
    {
        return [
            'uts' => ['required', 'string', 'max:60', 'unique:compras,uts'],
            'mes_ano' => ['required', 'date'],
            'fornecedor_nome' => ['required', 'string', 'max:180'],
            'fornecedor_cnpj' => ['required', 'string', new CnpjValido()],
            'armazem' => ['required', 'in:' . implode(',', array_keys(Compra::armazens()))],
            'certificacao' => ['required', 'in:' . implode(',', array_keys(Compra::certificacoes()))],
            'tipo_entrada' => ['nullable', 'string', 'max:40'],
            'volume_sacas' => ['required', 'numeric', 'min:0.01', 'max:999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'uts.unique' => 'Já existe uma compra cadastrada com esta referência (UTS).',
            'fornecedor_cnpj' => 'CNPJ inválido.',
        ];
    }
}
