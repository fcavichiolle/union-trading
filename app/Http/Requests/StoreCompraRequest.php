<?php

namespace App\Http\Requests;

use App\Models\Compra;
use App\Rules\CnpjValido;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin', 'compras') ?? false;
    }

    public function rules(): array
    {
        return [
            'uts' => ['required', 'string', 'max:60', 'unique:compras,uts'],
            'mes_ano' => ['required', 'date'],
            'fornecedor_nome' => ['required', 'string', 'max:180'],
            'fornecedor_cnpj' => ['required', 'string', new CnpjValido()],
            'armazem' => ['required', 'in:' . implode(',', array_keys(Compra::armazens()))],
            'certificacao' => ['required', 'in:' . implode(',', array_keys(Compra::certificacoes()))],
            'tipo_entrada' => ['nullable', 'string', 'max:40'],
            'volume_sacas' => ['required', 'numeric', 'min:0.01', 'max:999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'uts.required' => 'Informe a UTS (referência da compra).',
            'uts.unique' => 'Já existe uma compra cadastrada com esta UTS.',
            'mes_ano.required' => 'Informe o mês/ano da entrega.',
            'mes_ano.date' => 'O mês/ano da entrega não é uma data válida.',
            'fornecedor_nome.required' => 'Informe o nome do fornecedor.',
            // Atenção: a chave precisa do sufixo da regra. Sem ele
            // ('fornecedor_cnpj' => ...) a mensagem substitui TODAS as regras
            // do campo — era o bug que fazia um CNPJ em branco dizer
            // "CNPJ inválido" em vez de "informe o CNPJ".
            'fornecedor_cnpj.required' => 'Informe o CNPJ do fornecedor.',
            'armazem.required' => 'Selecione o armazém de entrega.',
            'certificacao.required' => 'Selecione a certificação.',
            'volume_sacas.required' => 'Informe o volume entregue, em sacas.',
            'volume_sacas.numeric' => 'O volume entregue deve ser um número (ex.: 600 ou 600,50).',
            'volume_sacas.min' => 'O volume entregue deve ser maior que zero.',
        ];
    }
}
