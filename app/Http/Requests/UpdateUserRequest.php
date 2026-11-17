<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($userId)],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'active' => ['required', 'boolean'],
            // Assinatura do e-mail de instrução de AA — tudo opcional: quem
            // não preencher assina só com nome, e-mail e site.
            'cargo' => ['nullable', 'string', 'max:80'],
            'cargo_en' => ['nullable', 'string', 'max:80'],
            'telefone' => ['nullable', 'string', 'max:40'],
            'celular' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do usuário.',
            'email.required' => 'Informe o e-mail do usuário.',
            'email.email' => 'Informe um e-mail válido (ex.: nome@utrading.com.br).',
            'email.unique' => 'Já existe outro usuário cadastrado com este e-mail.',
            'role_id.required' => 'Selecione o perfil de acesso.',
            'role_id.exists' => 'O perfil selecionado não existe.',
            'active.required' => 'Selecione se a conta fica ativa ou suspensa.',
        ];
    }
}
