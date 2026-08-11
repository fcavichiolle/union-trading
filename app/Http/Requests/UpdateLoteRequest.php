<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin', 'compras') ?? false;
    }

    public function rules(): array
    {
        return [
            'numero_lote' => ['required', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_lote.required' => 'Informe o número do lote dado pelo armazém.',
            'numero_lote.max' => 'O número do lote é muito longo (máximo de 60 caracteres).',
        ];
    }
}
