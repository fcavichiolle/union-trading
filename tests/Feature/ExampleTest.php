<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A raiz "/" não existe de propósito (a entrada do sistema é /login).
     * Visitante não autenticado é levado para a tela de login.
     */
    public function test_a_raiz_nao_existe_e_o_login_responde(): void
    {
        // Não há rota em "/": o app não tem página inicial pública.
        $this->get('/')->assertNotFound();

        // A porta de entrada de verdade responde normalmente.
        $this->get('/login')->assertOk();
    }
}
