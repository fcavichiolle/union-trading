<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
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
            'padrao_final' => ['required', 'in:FINE_CUP,GOOD_CUP'],

            'peneira_1718_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'peneira_1718_sacas' => ['required', 'numeric', 'min:0'],

            'peneira_1416_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'peneira_1416_sacas' => ['required', 'numeric', 'min:0'],

            'mercado_interno_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'mercado_interno_sacas' => ['required', 'numeric', 'min:0'],

            'grinders_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'grinders_sacas' => ['required', 'numeric', 'min:0'],
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
                + (float) ($data['grinders_pct'] ?? 0);

            if (abs($somaPct - 100) > 0.5) {
                $validator->errors()->add('peneira_1718_pct', 'A soma das porcentagens das peneiras deve totalizar 100% (atual: ' . number_format($somaPct, 2) . '%).');
            }

            $compra = $this->route('compra');
            if ($compra) {
                $somaSacas = (float) ($data['peneira_1718_sacas'] ?? 0)
                    + (float) ($data['peneira_1416_sacas'] ?? 0)
                    + (float) ($data['mercado_interno_sacas'] ?? 0)
                    + (float) ($data['grinders_sacas'] ?? 0);

                if ($somaSacas - (float) $compra->volume_sacas > 0.01) {
                    $validator->errors()->add('peneira_1718_sacas', 'A soma das sacas classificadas (' . number_format($somaSacas, 2) . ') não pode ultrapassar o volume entregue (' . number_format((float) $compra->volume_sacas, 2) . ').');
                }
            }
        });
    }
}
