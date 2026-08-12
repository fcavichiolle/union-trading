<?php

namespace App\Http\Requests;

use App\Models\Contrato;
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
            // Os dropdowns vêm do cadastro do admin; a fixação grava o nome.
            'corretora' => ['required', 'string', 'max:80', Rule::exists('corretoras', 'nome')->where('tipo', 'NOSSA')],
            'broker_cliente' => ['nullable', 'string', 'max:80', Rule::exists('corretoras', 'nome')->where('tipo', 'CLIENTE')],
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

            // Defesa em profundidade: a Tela NY já não lista cancelados.
            if ($cancelados = $contratos->filter(fn (Contrato $c) => $c->cancelado())->pluck('numero_ut')->all()) {
                $v->errors()->add('contratos', 'Contrato(s) cancelado(s) não podem ser fixados: UT ' . implode(', UT ', $cancelados) . '.');

                return;
            }

            if ($contratos->pluck('porto')->unique()->count() > 1) {
                $v->errors()->add('contratos', 'Fixação em grupo só entre contratos da mesma bolsa (todos Santos/NY ou todos Vitória/Londres).');

                return;
            }

            // Janela larga aqui também: fixação lançada com atraso pode ser
            // contra uma posição que acabou de vencer, e recusar isso
            // travaria o registro de uma operação que existiu de verdade.
            // O que a validação garante é que a tela é da BOLSA certa.
            $porto = $contratos->first()?->porto;
            $meses = $porto === 'VITORIA' ? Contrato::mesesFixacaoVitoriaTodas() : Contrato::mesesFixacaoSantosTodas();
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
            'contratos.*.exists' => 'Um dos contratos marcados não existe mais.',
            'contratos.*.distinct' => 'O mesmo contrato foi marcado duas vezes.',
            'corretora.required' => 'Selecione a corretora.',
            'corretora.exists' => 'Esta corretora não está no cadastro (Administração → Corretoras).',
            'broker_cliente.exists' => 'Este broker de cliente não está no cadastro (Administração → Corretoras).',
            'tela.required' => 'Escolha a tela (mês da bolsa) contra a qual está fixando.',
            'lotes.integer' => 'A quantidade de lotes deve ser um número inteiro.',
            'lotes.min' => 'É preciso fixar ao menos 1 lote.',
            'level.required' => 'Informe o level (preço da bolsa).',
            'level.numeric' => 'O level precisa ser um número (use ponto decimal, ex.: 335.00).',
            'diferenciais.required' => 'Informe o diferencial de cada contrato marcado.',
            'diferenciais.*.required' => 'Informe o diferencial de cada contrato marcado.',
            'diferenciais.*.numeric' => 'O diferencial precisa ser um número (use ponto decimal, ex.: -16.00).',
        ];
    }
}
