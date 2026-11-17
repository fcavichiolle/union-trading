<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Qualidade;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * O paginador. O padrão do Laravel é escrito para Tailwind, e o projeto não
 * tem Tailwind: quando "Contratos gerados" passou de 20 linhas pela primeira
 * vez (carga de nov/2026), as setas em SVG apareceram do tamanho da tela.
 * Nenhum teste pegava, porque nenhum criava registros suficientes para
 * paginar — este cria.
 */
class PaginacaoTest extends TestCase
{
    use RefreshDatabase;

    /** 45 contratos: 30 da ALFA (UT 6001–6030) e 15 da BETA (UT 6031–6045). */
    private function cenario(): User
    {
        $role = Role::create(['slug' => 'admin', 'nome' => 'Administrador']);
        $admin = User::create([
            'role_id' => $role->id, 'name' => 'Admin', 'email' => 'admin@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);
        $cliente = Cliente::create(['nome' => 'ALFA', 'endereco' => 'Madrid']);
        $qualidade = Qualidade::create(['descricao' => 'NY 3/4 SCREEN 14/16 GOOD CUP']);

        $outro = Cliente::create(['nome' => 'BETA NV', 'endereco' => 'Genebra']);

        for ($i = 1; $i <= 45; $i++) {
            $c = $i <= 30 ? $cliente : $outro;
            Contrato::create([
                'numero_ut' => (string) (6000 + $i), 'data_contrato' => '2026-08-05',
                'cliente_id' => $c->id, 'cliente_nome' => $c->nome, 'cliente_endereco' => $c->endereco,
                'buyer_ref' => 'ECT ' . (4000 + $i),
                'qualidade_id' => $qualidade->id, 'qualidade_descricao' => $qualidade->descricao,
                'tipo_cafe' => 'ARABICA', 'certificado' => 'RFA', 'quantidade_kg' => 19200,
                'tipo_container' => '20', 'embalagem' => 'Bulk Liner', 'incoterms' => 'FOB', 'porto' => 'SANTOS',
                'created_by' => $admin->id,
            ]);
        }

        return $admin;
    }

    public function test_lista_com_mais_de_uma_pagina_usa_o_paginador_do_sistema(): void
    {
        $admin = $this->cenario();

        $html = $this->actingAs($admin)->get(route('contratos.index', ['page' => 2]))->assertOk()->getContent();

        // Markup do sistema, com o resumo e a página atual marcada…
        $this->assertStringContainsString('class="paginacao"', $html);
        $this->assertStringContainsString('21–40 de 45', $html);
        $this->assertMatchesRegularExpression('/paginacao__item is-atual"[^>]*>2</', $html);
        $this->assertStringContainsString('Próxima', $html);

        // …e nada do paginador padrão: nem as setas em SVG, nem as classes Tailwind.
        preg_match('/<nav class="paginacao".*?<\/nav>/s', $html, $m);
        $this->assertStringNotContainsString('<svg', $m[0]);
        $this->assertStringNotContainsString('w-5 h-5', $html);
    }

    /** Busca em "Contratos gerados": UT, comprador ou ref. do comprador. */
    public function test_busca_por_ut_comprador_e_referencia(): void
    {
        $admin = $this->cenario();
        $buscar = fn (string $termo) => $this->actingAs($admin)
            ->get(route('contratos.index', ['busca' => $termo]))->assertOk()->viewData('contratos');

        $this->assertSame(15, $buscar('beta')->total());           // comprador, sem diferenciar caixa
        $this->assertSame(['6007'], $buscar('6007')->pluck('numero_ut')->all());   // UT
        $this->assertSame(['6012'], $buscar('ECT 4012')->pluck('numero_ut')->all()); // ref. do comprador
        $this->assertSame(0, $buscar('zzzz')->total());
    }

    /** Ir para a página 2 não pode perder o termo da busca. */
    public function test_paginacao_mantem_a_busca(): void
    {
        $admin = $this->cenario();

        $resposta = $this->actingAs($admin)->get(route('contratos.index', ['busca' => 'ALFA']))->assertOk();

        $this->assertSame(30, $resposta->viewData('contratos')->total());
        $resposta->assertSee('30 contratos encontrados')
            ->assertSee('busca=ALFA&amp;page=2', false);
    }
}
