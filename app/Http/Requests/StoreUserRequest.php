<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Reforço extra: mesmo que a rota já esteja atrás do middleware
        // 'role:admin', a autorização é checada de novo aqui dentro do
        // request (defesa em profundidade).
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            // Senha é opcional aqui: se não vier, o controller gera uma
            // senha temporária forte automaticamente.
            'password' => ['nullable', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Já existe um usuário cadastrado com este e-mail.',
        ];
    }
}
