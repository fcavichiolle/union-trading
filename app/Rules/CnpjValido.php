<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida CNPJ pelo algoritmo oficial de dígitos verificadores
 * (evita cadastrar fornecedor com CNPJ inventado/errado de digitação).
 */
class CnpjValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cnpj = preg_replace('/\D/', '', (string) $value);

        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            $fail('O :attribute informado não é válido — confira os 14 dígitos.');
            return;
        }

        if (! $this->digitosValidos($cnpj)) {
            $fail('O :attribute informado não é válido — confira os 14 dígitos.');
        }
    }

    private function digitosValidos(string $cnpj): bool
    {
        $calcular = function (string $base, array $pesos): int {
            $soma = 0;
            foreach ($pesos as $i => $peso) {
                $soma += ((int) $base[$i]) * $peso;
            }
            $resto = $soma % 11;
            return $resto < 2 ? 0 : 11 - $resto;
        };

        $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $digito1 = $calcular(substr($cnpj, 0, 12), $pesos1);

        $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $digito2 = $calcular(substr($cnpj, 0, 12) . $digito1, $pesos2);

        return $cnpj === substr($cnpj, 0, 12) . $digito1 . $digito2;
    }
}
