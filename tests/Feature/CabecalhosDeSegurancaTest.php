<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * O que a varredura de segurança de 17/11/2026 fechou e não pode reabrir
 * sem alguém perceber.
 */
class CabecalhosDeSegurancaTest extends TestCase
{
    public function test_toda_resposta_leva_a_politica_de_conteudo(): void
    {
        $resposta = $this->get(route('login'))->assertOk();

        $csp = $resposta->headers->get('Content-Security-Policy');

        // Só o próprio servidor: nada de script, conexão ou formulário para fora.
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("connect-src 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringNotContainsString('http', $csp);

        $resposta->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /** O disco privado (cargas e backups com dados reais) não tem rota HTTP. */
    public function test_disco_privado_nao_e_servido_por_http(): void
    {
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->get('/storage/qualquer-arquivo.json')->assertNotFound();
    }

    /** Telefone e e-mail do cabeçalho dos PDFs vêm do .env, não do código. */
    public function test_contato_da_empresa_nao_esta_no_codigo(): void
    {
        foreach (['contratos/pdf', 'contratos/carta-fixacao', 'embarques/programacao-pdf', 'ny/fixacoes-pdf'] as $view) {
            $fonte = file_get_contents(resource_path("views/{$view}.blade.php"));

            $this->assertDoesNotMatchRegularExpression('/[\w.-]+@[\w-]+\.[a-z.]{2,}/i', $fonte, "E-mail fixo em {$view}");
            $this->assertDoesNotMatchRegularExpression('/\+55 ?\d{2} ?\d{4,5}-\d{4}/', $fonte, "Telefone fixo em {$view}");
        }
    }
}
