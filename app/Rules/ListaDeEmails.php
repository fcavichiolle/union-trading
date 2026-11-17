<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Lista de e-mails separados por ponto e vírgula (ou vírgula), como as
 * listas de destinatários da planilha de AA:
 *
 *     mesa@corretora.example.com; brasil@corretora.example.com; copia@utrading.example.com
 *
 * Esta classe é a ÚNICA definição do que é uma lista de e-mails no sistema:
 * a validação do cadastro e a leitura pelos models usam o mesmo `separar()`.
 * Se os dois tivessem lógicas próprias, daria para salvar uma lista que o
 * envio depois interpretaria de outro jeito.
 *
 * Campo vazio passa — a lista é opcional em todo cadastro (corretora sem
 * e-mail apenas não oferece o botão de AA, com aviso na tela).
 */
class ListaDeEmails implements ValidationRule
{
    /**
     * Quebra a lista em endereços, descartando espaços e itens vazios.
     *
     * @return array<int, string>
     */
    public static function separar(?string $lista): array
    {
        if ($lista === null || trim($lista) === '') {
            return [];
        }

        $partes = preg_split('/[;,]/', $lista) ?: [];

        return array_values(array_filter(array_map('trim', $partes), fn ($e) => $e !== ''));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $invalidos = array_filter(
            self::separar(is_string($value) ? $value : null),
            fn (string $email) => ! filter_var($email, FILTER_VALIDATE_EMAIL)
        );

        if ($invalidos !== []) {
            $fail('Confira os endereços: ' . implode(', ', $invalidos)
                . '. Separe vários e-mails por ponto e vírgula (;).');
        }
    }
}
