<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()],
        ];
    }

    public function messages(): array
    {
        return [
            // Sufixo da regra é obrigatório: sem ele ('current_password' => ...)
            // a mensagem valeria também para o campo em branco, dizendo
            // "está incorreta" quando o certo é pedir para preencher.
            'current_password.required' => 'Informe sua senha atual.',
            'current_password.current_password' => 'A senha atual informada está incorreta.',
            'password.required' => 'Informe a nova senha.',
            'password.confirmed' => 'A nova senha e a confirmação não são iguais.',
            'password.min' => 'A nova senha deve ter pelo menos 12 caracteres.',
            'password.uncompromised' => 'Essa senha apareceu em vazamentos de dados conhecidos. Escolha outra.',
        ];
    }
}
