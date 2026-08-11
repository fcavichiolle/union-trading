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
            'numero_ut.required' => 'Informe o número UT do contrato.',
            'numero_ut.unique' => 'Já existe um contrato com este número UT.',
            'data_contrato.required' => 'Informe a data do contrato.',
            'cliente_id.required' => 'Selecione o cliente (comprador).',
            'cliente_id.exists' => 'O cliente selecionado não está mais cadastrado.',
            'qualidade_id.required' => 'Selecione a qualidade do café.',
            'qualidade_id.exists' => 'A qualidade selecionada não está mais cadastrada.',
            'tipo_cafe.required' => 'Selecione o tipo de café (arábica ou conilon).',
            'certificado.required' => 'Selecione o certificado.',
            'quantidade_kg.required' => 'Informe a quantidade em quilos.',
            'quantidade_kg.numeric' => 'A quantidade deve ser um número em quilos (ex.: 108000).',
            'quantidade_kg.min' => 'A quantidade em quilos deve ser maior que zero.',
            'tipo_container.required' => 'Selecione o tipo de container (20\' ou 40\').',
            'embalagem.required' => 'Selecione a embalagem.',
            'porto.required' => 'Selecione o porto de embarque.',
            'embarque_mes.regex' => 'Informe o mês de embarque no formato mês/ano.',
            'mes_fixacao.in' => 'O mês de fixação escolhido não pertence à bolsa deste porto.',
            'preco_fixado.required_if' => 'Informe o preço fixado (contrato marcado como FIXED).',
            'preco_fixado.numeric' => 'O preço fixado deve ser um número (ex.: 353.40).',
            'preco_fixado_unidade.required_if' => 'Informe a unidade do preço fixado (cts/lb ou USD/MT).',
            'remarks.max' => 'As observações passaram do limite de 2.000 caracteres.',
        ];
    }
}
