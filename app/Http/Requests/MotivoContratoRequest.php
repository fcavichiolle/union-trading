<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ações do contrato que mexem na sua situação — cancelar, reativar e
 * excluir. Nas três o motivo é obrigatório: é o que explica a decisão para
 * quem consultar depois.
 *
 * Onde o motivo fica guardado muda conforme a ação:
 *  - cancelar  => no próprio contrato (`motivo_cancelamento`) e no AuditLog;
 *  - reativar  => só no AuditLog (o contrato volta a ficar "limpo");
 *  - excluir   => só no AuditLog, único lugar que sobrevive ao registro sumir.
 */
class MotivoContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin', 'compras') ?? false;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Informe o motivo — é o que explica esta ação para quem consultar o contrato depois.',
            'motivo.min' => 'Descreva o motivo com um pouco mais de detalhe (mínimo de 5 caracteres).',
            'motivo.max' => 'O motivo passou do limite de 500 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return ['motivo' => 'motivo'];
    }
}
