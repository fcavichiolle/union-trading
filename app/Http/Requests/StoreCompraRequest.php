<?php

namespace App\Http\Requests;

use App\Models\Classificacao;
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

    /**
     * O usuário informa sacas ou peso — o outro é calculado antes da
     * validação, senão "volume contratado é obrigatório" apareceria para
     * quem preencheu só os quilos.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(Compra::completarSacasEPeso($this->all(), 'volume_contratado'));
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
            // Armazém PREVISTO: opcional, porque o destino às vezes só é
            // definido na hora de entregar. Quem vale para o estoque é o
            // armazém de cada entrega.
            'armazem_id' => ['nullable', 'integer', Rule::exists('armazens', 'id')],
            'tipo_entrada' => ['required', Rule::in(array_keys(Compra::tiposEntrada()))],
            // Qualidade só se aplica ao arábica: conilon não passa pela
            // peneira, e o formulário esconde os dois campos.
            // Exigido quando o tipo é ARABICA de fato — não "quando não é
            // conilon": com o tipo em branco, cobrar padrão e bebida seria
            // empilhar três erros em cima de um campo que nem foi enviado.
            'padrao_final' => [
                Rule::requiredIf(fn () => $this->input('tipo_entrada') === 'ARABICA'),
                'nullable',
                Rule::in(array_keys(Classificacao::padroes())),
            ],
            'tipo_bebida' => [
                Rule::requiredIf(fn () => $this->input('tipo_entrada') === 'ARABICA'),
                'nullable',
                Rule::in(array_keys(Classificacao::tiposBebida())),
            ],
            // Sacas OU peso: quem não vier é calculado a 60 kg/saca (ver
            // prepareForValidation).
            'volume_contratado' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'peso_kg' => ['nullable', 'numeric', 'min:0.01', 'max:99999999'],
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
            'tipo_entrada.required' => 'Selecione o tipo de café (arábica ou conilon).',
            'padrao_final.required' => 'Selecione o padrão final (obrigatório para arábica).',
            'tipo_bebida.required' => 'Selecione o tipo de bebida (obrigatório para arábica).',
            'volume_contratado.required' => 'Informe o volume contratado, em sacas ou em quilos.',
            'peso_kg.numeric' => 'O peso deve ser um número, em quilos (ex.: 30000 ou 30000,50).',
            'volume_contratado.numeric' => 'O volume contratado deve ser um número (ex.: 500 ou 500,50).',
            'volume_contratado.min' => 'O volume contratado deve ser maior que zero.',
            'valor_saca.numeric' => 'O preço da saca deve ser um número (ex.: 1630 ou 1630,50).',
            'comissao_pct.numeric' => 'A comissão deve ser um número em porcentagem (ex.: 0,5).',
            'comissao_pct.max' => 'A comissão não pode passar de 100%.',
        ];
    }
}
