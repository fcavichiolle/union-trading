<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinanceiroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin', 'compras', 'financeiro') ?? false;
    }

    public function rules(): array
    {
        return [
            'valor_saca' => ['required', 'numeric', 'min:0', 'max:999999'],
            'corretor_nome' => ['nullable', 'string', 'max:150'],
            'comissao_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'valor_saca.required' => 'Informe o valor da saca.',
            'valor_saca.numeric' => 'O valor da saca deve ser um número (ex.: 1200 ou 1200,50).',
            'valor_saca.min' => 'O valor da saca não pode ser negativo.',
            'comissao_pct.numeric' => 'A comissão deve ser um número em porcentagem (ex.: 1,5).',
            'comissao_pct.max' => 'A comissão não pode passar de 100%.',
        ];
    }
}
