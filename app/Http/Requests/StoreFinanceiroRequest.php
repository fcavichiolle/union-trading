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
}
