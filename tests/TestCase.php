<?php

namespace Tests;

use App\Models\Armazem;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Id de um armazém pelo apelido curto usado nos testes.
     *
     * Armazéns deixaram de ser ENUM e viraram cadastro; a migration semeia
     * os três que existiam, então em teste basta traduzir o apelido para o
     * id. Assim os testes continuam legíveis (`$this->armazem('SAAG')`) sem
     * depender da ordem de inserção.
     */
    protected function armazem(string $apelido): int
    {
        $nomes = [
            'SAAG' => 'SAAG',
            'QUALITE' => 'QUALITÉ',
            'DINAMO_MACHADO' => 'DÍNAMO MACHADO',
        ];

        $nome = $nomes[$apelido] ?? $apelido;

        return Armazem::where('nome', $nome)->value('id')
            ?? Armazem::create(['nome' => $nome, 'cidade' => 'Cidade', 'estado' => 'MG'])->id;
    }
}
