<?php

namespace App\Http\Requests;

use App\Models\Compra;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Entrada física no armazém — é aqui que o funcionário 3 registra o que
 * REALMENTE chegou e o número do lote.
 *
 * O volume NÃO é limitado pelo contratado de propósito: o armazém pode
 * receber mais ou menos do que foi negociado, e quem confere é ele. A
 * diferença aparece como informação na tela da compra, não como erro.
 */
class StoreEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin', 'compras') ?? false;
    }

    /**
     * O armazém informa sacas OU peso — o que faltar é calculado antes da
     * validação (60 kg/saca), senão pediríamos as sacas de quem já disse
     * quantos quilos entraram.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(Compra::completarSacasEPeso($this->all(), 'volume_sacas'));
    }

    public function rules(): array
    {
        return [
            // Data completa: a auditoria precisa do dia da entrada.
            'data_entrega' => ['required', 'date'],
            'armazem' => ['required', Rule::in(array_keys(Compra::armazens()))],
            'volume_sacas' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'peso_kg' => ['nullable', 'numeric', 'min:0.01', 'max:99999999'],
            'numero_lote' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'data_entrega.required' => 'Informe a data da entrega.',
            'data_entrega.date' => 'Informe uma data válida para a entrega.',
            'armazem.required' => 'Selecione o armazém que recebeu o café.',
            'volume_sacas.required' => 'Informe quantas sacas (ou quantos quilos) entraram no armazém.',
            'volume_sacas.numeric' => 'O volume entregue deve ser um número (ex.: 480 ou 480,50).',
            'volume_sacas.min' => 'O volume entregue deve ser maior que zero.',
            'peso_kg.numeric' => 'O peso deve ser um número, em quilos (ex.: 28800 ou 28800,50).',
            'numero_lote.max' => 'O número do lote é muito longo (máximo de 60 caracteres).',
        ];
    }
}
