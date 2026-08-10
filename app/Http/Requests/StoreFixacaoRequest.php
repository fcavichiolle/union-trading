<?php

namespace App\Http\Requests;

use App\Models\Contrato;
use App\Models\Fixacao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Fixação de um OU vários contratos de uma vez ("fixação em grupo").
 * Com 1 contrato marcado, pode ser parcial (campo `lotes`); com vários,
 * fixa TODOS os lotes restantes de cada um, com o mesmo level/corretora/
 * tela — o diferencial é por contrato (`diferenciais[id]`), porque cada
 * contrato foi fechado com o seu.
 */
class StoreFixacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin', 'compras') ?? false;
    }

    public function rules(): array
    {
        return [
            'contratos' => ['required', 'array', 'min:1'],
            'contratos.*' => ['integer', 'distinct', 'exists:contratos,id'],
            'corretora' => ['required', Rule::in(array_keys(Fixacao::corretoras()))],
            'broker_cliente' => ['nullable', Rule::in(array_keys(Fixacao::brokersCliente()))],
            'tela' => ['required', 'string', 'max:20'], // pertencer à bolsa certa é checado abaixo
            'lotes' => ['nullable', 'integer', 'min:1'], // exigido só no modo de 1 contrato
            'level' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'diferenciais' => ['required', 'array'],
            'diferenciais.*' => ['required', 'numeric', 'min:-9999.99', 'max:9999.99'],
        ];
    }

    /**
     * Regras que dependem dos contratos marcados: nenhum já FIXED, todos
     * da mesma bolsa, tela pertencente a essa bolsa, diferencial informado
     * para cada um e — no modo de 1 contrato — lotes dentro do restante.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $ids = array_map('intval', (array) $this->input('contratos', []));
            $contratos = Contrato::whereIn('id', $ids)->get();

            if ($jaFixados = $contratos->where('fixado', true)->pluck('numero_ut')->all()) {
                $v->errors()->add('contratos', 'Contrato(s) já totalmente fixado(s): UT ' . implode(', UT ', $jaFixados) . '.');

                return;
            }

            if ($contratos->pluck('porto')->unique()->count() > 1) {
                $v->errors()->add('contratos', 'Fixação em grupo só entre contratos da mesma bolsa (todos Santos/NY ou todos Vitória/Londres).');

                return;
            }

            $porto = $contratos->first()?->porto;
            $meses = $porto === 'VITORIA' ? Contrato::mesesFixacaoVitoria() : Contrato::mesesFixacaoSantos();
            if (! array_key_exists($this->input('tela'), $meses)) {
                $v->errors()->add('tela', 'Esta tela não pertence à bolsa dos contratos marcados.');
            }

            foreach ($contratos as $c) {
                if (! is_numeric($this->input("diferenciais.{$c->id}"))) {
                    $v->errors()->add('diferenciais', "Informe o diferencial do contrato UT {$c->numero_ut}.");
                }
            }

            // Modo 1 contrato: fixação parcial permitida, limitada ao restante.
            if ($contratos->count() === 1) {
                $restantes = $contratos->first()->lotesRestantes();
                if (! $this->filled('lotes')) {
                    $v->errors()->add('lotes', 'Informe quantos lotes fixar.');
                } elseif ($this->integer('lotes') > $restantes) {
                    $v->errors()->add('lotes', "Este contrato tem apenas {$restantes} lote(s) restante(s) para fixar.");
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'contratos.required' => 'Marque ao menos um contrato para fixar.',
            'tela.required' => 'Escolha a tela (mês da bolsa) contra a qual está fixando.',
            'level.required' => 'Informe o level (preço da bolsa).',
            'diferenciais.required' => 'Informe o diferencial de cada contrato marcado.',
            'diferenciais.*.required' => 'Informe o diferencial de cada contrato marcado.',
            'diferenciais.*.numeric' => 'O diferencial precisa ser um número (use ponto decimal, ex.: -16.00).',
        ];
    }
}
