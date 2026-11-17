<?php

namespace App\Http\Requests;

use App\Models\Embarque;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Criação e edição de embarque — as regras são as mesmas nos dois. */
class EmbarqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin', 'compras') ?? false;
    }

    public function rules(): array
    {
        return [
            'sufixo' => ['nullable', 'string', 'max:5'],
            // <input type="month"> manda "2026-11".
            'mes_faturamento' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'quantidade_kg' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'status' => ['required', Rule::in(array_keys(Embarque::statuses()))],
            'pss' => ['nullable', Rule::in(array_keys(Embarque::pssOpcoes()))],
            'pss_obs' => ['nullable', 'string', 'max:120'],
            'armazem_id' => ['nullable', 'exists:armazens,id'],
            'transportadora' => ['nullable', 'string', 'max:60'],
            'data_fumigacao' => ['nullable', 'date'],
            'data_estufagem' => ['nullable', 'date'],
            'deadline_documentos' => ['nullable', 'date'],
            'deadline_carga' => ['nullable', 'date'],
            'data_fitossanitario' => ['nullable', 'date'],
            'destino' => ['nullable', 'string', 'max:80'],
            'etd' => ['nullable', 'date'],
            'navio' => ['nullable', 'string', 'max:80'],
            'booking' => ['nullable', 'string', 'max:120'],
            'pilha' => ['nullable', 'string', 'max:80'],
            'faturado_em' => ['nullable', 'date'],
            'baixado_em' => ['nullable', 'date', 'before_or_equal:today'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'ico' => ['nullable', 'string', 'max:40'],
            'fumigacao_empresa' => ['nullable', 'string', 'max:60'],
            'fumigacao_tipo' => ['nullable', 'string', 'max:60'],
            'fumigacao_tempo' => ['nullable', 'string', 'max:20'],
            'permit_import' => ['nullable', 'string', 'max:40'],
            'instrucoes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'mes_faturamento.required' => 'Informe o mês de faturamento do embarque.',
            'mes_faturamento.regex' => 'Informe o mês de faturamento no formato mês/ano.',
            'quantidade_kg.required' => 'Informe a quantidade do embarque em quilos.',
            'quantidade_kg.numeric' => 'A quantidade deve ser um número em quilos (ex.: 19200).',
            'quantidade_kg.min' => 'A quantidade em quilos deve ser maior que zero.',
            'status.required' => 'Selecione a etapa do embarque.',
            'status.in' => 'Etapa de embarque inválida.',
            'armazem_id.exists' => 'O armazém selecionado não está mais cadastrado.',
            'baixado_em.before_or_equal' => 'A data da baixa do estoque não pode ser no futuro.',
        ];
    }

    /** Dados prontos para o model: o mês vira data (dia 01). */
    public function dados(): array
    {
        $dados = $this->validated();
        $dados['mes_faturamento'] = $dados['mes_faturamento'] . '-01';

        return $dados;
    }
}
