<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Aceita CNPJ (14 dígitos) ou CPF (11), validando os dígitos verificadores
 * pelo algoritmo oficial — evita cadastrar fornecedor com documento
 * inventado ou com erro de digitação.
 *
 * Campo vazio passa: o documento é opcional (vendedor "a confirmar"),
 * quem exige é a regra 'required' quando for o caso.
 */
class DocumentoValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digitos = preg_replace('/\D/', '', (string) $value);

        if ($digitos === '') {
            return;
        }

        $valido = match (strlen($digitos)) {
            14 => $this->cnpjValido($digitos),
            11 => $this->cpfValido($digitos),
            default => false,
        };

        if (! $valido) {
            $fail('Informe um CNPJ (14 dígitos) ou CPF (11 dígitos) válido — confira os números digitados.');
        }
    }

    private function cnpjValido(string $cnpj): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $d1 = $this->digito(substr($cnpj, 0, 12), [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $d2 = $this->digito(substr($cnpj, 0, 12) . $d1, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return $cnpj === substr($cnpj, 0, 12) . $d1 . $d2;
    }

    private function cpfValido(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        $d1 = $this->digito(substr($cpf, 0, 9), [10, 9, 8, 7, 6, 5, 4, 3, 2]);
        $d2 = $this->digito(substr($cpf, 0, 9) . $d1, [11, 10, 9, 8, 7, 6, 5, 4, 3, 2]);

        return $cpf === substr($cpf, 0, 9) . $d1 . $d2;
    }

    /** Dígito verificador pelo módulo 11 (mesma conta para CPF e CNPJ). */
    private function digito(string $base, array $pesos): int
    {
        $soma = 0;
        foreach ($pesos as $i => $peso) {
            $soma += ((int) $base[$i]) * $peso;
        }
        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }
}
