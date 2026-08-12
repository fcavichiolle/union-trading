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

    /** Conilon não tem padrão de arábica nem tipo de bebida. */
    private function ehConilon(): bool
    {
        return (bool) $this->route('compra')?->ehConilon();
    }

    public function rules(): array
    {
        $regras = [
            'padrao_final' => [
                $this->ehConilon() ? 'nullable' : 'required',
                Rule::in(array_keys(Classificacao::padroes())),
            ],
            'tipo_bebida' => [
                $this->ehConilon() ? 'nullable' : 'required',
                Rule::in(array_keys(Classificacao::tiposBebida())),
            ],
        ];

        // As faixas vêm da lista central: peneira nova em
        // Classificacao::faixas() já entra validada, sem editar este arquivo.
        foreach (array_keys(Classificacao::faixas()) as $faixa) {
            $regras[$faixa . '_pct'] = ['required', 'numeric', 'min:0', 'max:100'];
            $regras[$faixa . '_sacas'] = ['required', 'numeric', 'min:0'];
        }

        return $regras;
    }

    public function messages(): array
    {
        return [
            'padrao_final.required' => 'Selecione o padrão final da classificação.',
            'padrao_final.in' => 'O padrão final selecionado não existe na lista.',
            'tipo_bebida.required' => 'Selecione o tipo de bebida.',
            'tipo_bebida.in' => 'O tipo de bebida selecionado não existe na lista.',
            // Todas as linhas de peneira usam as mesmas regras: uma mensagem
            // por regra (com :attribute) cobre todas sem repetir campo a campo.
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
            $dados = $validator->getData();
            $faixas = array_keys(Classificacao::faixas());

            $somar = fn (string $sufixo) => array_sum(array_map(
                fn (string $faixa) => (float) ($dados[$faixa . $sufixo] ?? 0),
                $faixas
            ));

            // Erro de soma tem chave própria ('soma_pct'/'soma_sacas') e é
            // mostrado acima da tabela: antes ele era pendurado na primeira
            // peneira, o que virava mensagem no campo errado — e o "primeiro"
            // muda a cada faixa nova no topo da lista.
            $somaPct = $somar('_pct');

            if (abs($somaPct - 100) > 0.5) {
                $validator->errors()->add(
                    'soma_pct',
                    'A soma das porcentagens das peneiras deve totalizar 100% (atual: ' . number_format($somaPct, 2) . '%).'
                );
            }

            $compra = $this->route('compra');

            if ($compra) {
                $somaSacas = $somar('_sacas');

                // Teto = o maior entre o contratado e o que já entrou. A
                // classificação é da UTS inteira e pode ser feita antes de
                // tudo chegar (teto = contratado), mas o armazém também pode
                // receber mais do que o contratado (teto = entregue).
                $teto = max((float) $compra->volume_contratado, $compra->sacasEntregues());

                if ($somaSacas - $teto > 0.01) {
                    $validator->errors()->add(
                        'soma_sacas',
                        'A soma das sacas classificadas (' . number_format($somaSacas, 2)
                            . ') não pode ultrapassar o volume da UTS (' . number_format($teto, 2) . ').'
                    );
                }
            }
        });
    }
}
