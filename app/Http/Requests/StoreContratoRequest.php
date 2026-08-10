<?php

namespace App\Http\Requests;

use App\Models\Contrato;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin', 'compras') ?? false;
    }

    public function rules(): array
    {
        return [
            'numero_ut' => ['required', 'string', 'max:40', 'unique:contratos,numero_ut'],
            'data_contrato' => ['required', 'date'],
            'cliente_id' => ['required', 'exists:clientes,id'],
            'buyer_ref' => ['nullable', 'string', 'max:80'],
            'qualidade_id' => ['required', 'exists:qualidades,id'],
            'tipo_cafe' => ['required', Rule::in(['ARABICA', 'CONILON'])],
            'certificado' => ['required', Rule::in(array_keys(Contrato::certificados()))],
            'quantidade_kg' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'tipo_container' => ['required', Rule::in(['20', '40'])],
            'embalagem' => ['required', Rule::in(Contrato::embalagens())],
            'fixado' => ['nullable', 'boolean'],
            'preco_fixado' => ['required_if:fixado,1', 'nullable', 'numeric', 'min:0', 'max:99999.99'],
            'preco_fixado_unidade' => ['required_if:fixado,1', 'nullable', Rule::in(array_keys(Contrato::unidadesPreco()))],
            'diferencial' => ['nullable', 'string', 'max:40'],
            'mes_fixacao' => ['nullable', Rule::in(array_keys(Contrato::mesesFixacao()))],
            'embarque_mes' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'incoterms' => ['required', Rule::in(array_keys(Contrato::incotermsLista()))],
            'porto' => ['required', Rule::in(array_keys(Contrato::portos()))],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_ut.unique' => 'Já existe um contrato com este número UT.',
            'preco_fixado.required_if' => 'Informe o preço fixado (contrato marcado como FIXED).',
            'preco_fixado_unidade.required_if' => 'Informe a unidade do preço fixado (cts/lb ou USD/MT).',
        ];
    }
}
