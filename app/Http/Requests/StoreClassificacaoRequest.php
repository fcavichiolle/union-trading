<?php

namespace App\Http\Requests;

use App\Models\Classificacao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreClassificacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin', 'compras') ?? false;
    }

    public function rules(): array
    {
        return [
            'padrao_final' => ['required', Rule::in(array_keys(Classificacao::padroes()))],
            'tipo_bebida' => ['required', Rule::in(array_keys(Classificacao::tiposBebida()))],

            'peneira_1718_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'peneira_1718_sacas' => ['required', 'numeric', 'min:0'],

            'peneira_1416_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'peneira_1416_sacas' => ['required', 'numeric', 'min:0'],

            'mercado_interno_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'mercado_interno_sacas' => ['required', 'numeric', 'min:0'],

            'grinders_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'grinders_sacas' => ['required', 'numeric', 'min:0'],

            'moka_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'moka_sacas' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'padrao_final.required' => 'Selecione o padrão final da classificação.',
            'padrao_final.in' => 'O padrão final selecionado não existe na lista.',
            'tipo_bebida.required' => 'Selecione o tipo de bebida.',
            'tipo_bebida.in' => 'O tipo de bebida selecionado não existe na lista.',
            // As 10 linhas de peneira usam as mesmas regras: uma mensagem por
            // regra (com :attribute) cobre todas sem repetir campo por campo.
            'required' => 'Preencha :attribute (use 0 se não houver).',
            'numeric' => 'O valor de :attribute deve ser um número.',
            'min' => 'O valor de :attribute não pode ser negativo.',
            'max' => 'A porcentagem em :attribute não pode passar de 100%.',
        ];
    }

    /**
     * Regras "de negócio" que cruzam campos: a soma das porcentagens
     * deve fechar em 100% e a soma das sacas não pode passar do volume
     * comprado. Ficam aqui (backend) porque validação só no JS pode ser
     * burlada por quem enviar a requisição direto.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();

            $somaPct = (float) ($data['peneira_1718_pct'] ?? 0)
                + (float) ($data['peneira_1416_pct'] ?? 0)
                + (float) ($data['mercado_interno_pct'] ?? 0)
                + (float) ($data['grinders_pct'] ?? 0)
                + (float) ($data['moka_pct'] ?? 0);

            if (abs($somaPct - 100) > 0.5) {
                $validator->errors()->add('peneira_1718_pct', 'A soma das porcentagens das peneiras deve totalizar 100% (atual: ' . number_format($somaPct, 2) . '%).');
            }

            $compra = $this->route('compra');
            if ($compra) {
                $somaSacas = (float) ($data['peneira_1718_sacas'] ?? 0)
                    + (float) ($data['peneira_1416_sacas'] ?? 0)
                    + (float) ($data['mercado_interno_sacas'] ?? 0)
                    + (float) ($data['grinders_sacas'] ?? 0)
                    + (float) ($data['moka_sacas'] ?? 0);

                // Teto = o maior entre o contratado e o que já entrou. A
                // classificação é da UTS inteira e pode ser feita antes de
                // tudo chegar (teto = contratado), mas o armazém também pode
                // receber mais do que o contratado (teto = entregue).
                $teto = max((float) $compra->volume_contratado, $compra->sacasEntregues());

                if ($somaSacas - $teto > 0.01) {
                    $validator->errors()->add(
                        'peneira_1718_sacas',
                        'A soma das sacas classificadas (' . number_format($somaSacas, 2)
                            . ') não pode ultrapassar o volume da UTS (' . number_format($teto, 2) . ').'
                    );
                }
            }
        });
    }
}
