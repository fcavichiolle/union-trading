<?php

namespace App\Http\Requests;

use App\Models\Contrato;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Edição de contrato já lançado. Herda as regras da criação, trocando
 * apenas o que muda ao editar:
 *  - o número UT continua único, mas ignorando o próprio contrato;
 *  - não dá para reduzir a quantidade a ponto de o contrato ficar com
 *    MENOS lotes do que já foram fixados na Tela NY (a fixação é operação
 *    de mercado já executada — o contrato não pode "encolher" abaixo dela).
 */
class UpdateContratoRequest extends StoreContratoRequest
{
    private function contrato(): Contrato
    {
        return $this->route('contrato');
    }

    public function rules(): array
    {
        $regras = parent::rules();

        $regras['numero_ut'] = [
            'required', 'string', 'max:40',
            Rule::unique('contratos', 'numero_ut')->ignore($this->contrato()->id),
        ];

        return $regras;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $contrato = $this->contrato();

            if ($contrato->cancelado()) {
                $v->errors()->add('numero_ut', 'Este contrato está cancelado e não pode ser editado.');

                return;
            }

            $lotesFixados = $contrato->lotesFixados();
            if ($lotesFixados === 0) {
                return;
            }

            // Simula o cálculo do model (Contrato::saving) para saber com
            // quantos lotes o contrato ficaria depois da edição.
            $novosLotes = $this->lotesPrevistos();

            if ($novosLotes < $lotesFixados) {
                $v->errors()->add('quantidade_kg', sprintf(
                    'Esta quantidade daria %d lote(s), mas o contrato já tem %d lote(s) fixado(s) na Tela NY. '
                        . 'Exclua a fixação antes de reduzir a quantidade.',
                    $novosLotes,
                    $lotesFixados
                ));
            }
        });
    }

    /** Mesmo cálculo do Contrato::saving(), para validar antes de gravar. */
    private function lotesPrevistos(): int
    {
        $kgPorSaca = $this->input('embalagem') === 'Jute Bags 59kg' ? 59 : Contrato::KG_POR_SACA;
        $sacas = round((float) $this->input('quantidade_kg') / $kgPorSaca, 2);

        $divisor = $this->input('tipo_cafe') === 'CONILON'
            ? Contrato::DIVISOR_CONILON
            : Contrato::DIVISOR_ARABICA;

        return (int) round($sacas / $divisor);
    }
}
