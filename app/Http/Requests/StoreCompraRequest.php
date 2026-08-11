<?php

namespace App\Http\Requests;

use App\Models\Compra;
use App\Rules\DocumentoValido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lançamento do NEGÓCIO (o que a funcionária 2 recebe do funcionário 1).
 * O que chegou no armazém não entra aqui — é registrado como Entrega.
 *
 * Preço e documento do fornecedor são opcionais de propósito: a mesa fecha
 * compra com vendedor "a confirmar" e às vezes sem o preço definido. O que
 * falta vira pendência no painel em vez de impedir o lançamento.
 */
class StoreCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin', 'compras') ?? false;
    }

    public function rules(): array
    {
        return [
            'uts' => ['required', 'string', 'max:60', Rule::unique('compras', 'uts')->ignore($this->route('compra'))],
            'data_compra' => ['required', 'date'],
            'fornecedor_nome' => ['required', 'string', 'max:180'],
            'fornecedor_documento' => ['nullable', 'string', new DocumentoValido()],
            'certificacao' => ['required', Rule::in(array_keys(Compra::certificacoes()))],
            'logistica' => ['nullable', Rule::in(array_keys(Compra::logisticas()))],
            'tipo_entrada' => ['nullable', 'string', 'max:40'],
            'volume_contratado' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'valor_saca' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'corretor_nome' => ['nullable', 'string', 'max:150'],
            'comissao_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pagamento_previsto' => ['nullable', 'date'],
            'pagamento_obs' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'uts.required' => 'Informe a UTS (referência da compra).',
            'uts.unique' => 'Já existe uma compra cadastrada com esta UTS.',
            'data_compra.required' => 'Informe a data da compra.',
            'fornecedor_nome.required' => 'Informe o nome do vendedor (se ainda não souber o documento, deixe o CNPJ/CPF em branco).',
            'certificacao.required' => 'Selecione a certificação.',
            'volume_contratado.required' => 'Informe o volume contratado, em sacas.',
            'volume_contratado.numeric' => 'O volume contratado deve ser um número (ex.: 500 ou 500,50).',
            'volume_contratado.min' => 'O volume contratado deve ser maior que zero.',
            'valor_saca.numeric' => 'O preço da saca deve ser um número (ex.: 1630 ou 1630,50).',
            'comissao_pct.numeric' => 'A comissão deve ser um número em porcentagem (ex.: 0,5).',
            'comissao_pct.max' => 'A comissão não pode passar de 100%.',
        ];
    }
}
