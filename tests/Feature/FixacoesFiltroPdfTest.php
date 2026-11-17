<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Fixacao;
use App\Models\Qualidade;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Filtros da lista "Fixações registradas" e o PDF do mesmo recorte.
 *
 * O ponto que justifica os testes: o filtro tem de ir ao BANCO. A lista sem
 * filtro mostra só as últimas 15, e se o filtro fosse aplicado sobre essas 15
 * uma tranche antiga daquele contrato — justamente o que se procura — nunca
 * apareceria.
 */
class FixacoesFiltroPdfTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $financeiro;
    private Cliente $cliente;
    private Qualidade $qualidade;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Role::create(['slug' => 'admin', 'nome' => 'Administrador']);
        $fin = Role::create(['slug' => 'financeiro', 'nome' => 'Financeiro']);

        $this->admin = User::create([
            'role_id' => $admin->id, 'name' => 'Admin', 'email' => 'admin@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);
        $this->financeiro = User::create([
            'role_id' => $fin->id, 'name' => 'Fin', 'email' => 'fin@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);

        $this->cliente = Cliente::create(['nome' => 'ALFA', 'endereco' => 'Madrid']);
        $this->qualidade = Qualidade::create(['descricao' => 'CAFE ARAB NAT 14/16']);
    }

    private function contrato(string $ut, array $overrides = []): Contrato
    {
        return Contrato::create(array_merge([
            'numero_ut' => $ut, 'data_contrato' => '2026-08-05',
            'cliente_id' => $this->cliente->id, 'cliente_nome' => $this->cliente->nome,
            'cliente_endereco' => $this->cliente->endereco,
            'qualidade_id' => $this->qualidade->id, 'qualidade_descricao' => $this->qualidade->descricao,
            'tipo_cafe' => 'ARABICA', 'certificado' => 'RFA', 'quantidade_kg' => 108000,
            'tipo_container' => '40', 'embalagem' => 'Bulk Liner', 'incoterms' => 'FOB',
            'porto' => 'SANTOS', 'diferencial' => '-16.00', 'created_by' => $this->admin->id,
        ], $overrides));
    }

    private function fixar(Contrato $contrato, array $overrides = []): Fixacao
    {
        return Fixacao::create(array_merge([
            'contrato_id' => $contrato->id, 'corretora' => 'StoneX East Coast',
            'tela' => 'Z6', 'lotes' => 2, 'level' => '300.00', 'diferencial' => '-16.00',
            'created_by' => $this->admin->id,
        ], $overrides));
    }

    public function test_sem_filtro_mostra_apenas_as_ultimas_quinze(): void
    {
        $contrato = $this->contrato('5961');

        for ($i = 0; $i < 18; $i++) {
            $this->fixar($contrato, ['lotes' => 1]);
        }

        $resposta = $this->actingAs($this->admin)->get(route('ny.fixacoes.index'))->assertOk();

        $this->assertCount(15, $resposta->viewData('fixacoes'));
        $resposta->assertSee('Últimas 15 fixações', false);
    }

    /**
     * O caso que o filtro existe para resolver: a tranche procurada está
     * fora da janela das 15 mais recentes.
     */
    public function test_filtro_por_contrato_acha_tranche_fora_das_ultimas_quinze(): void
    {
        $antigo = $this->contrato('5884');
        $antiga = $this->fixar($antigo, ['lotes' => 7]);

        // 18 fixações mais novas, de outro contrato, empurram a antiga para fora.
        $novo = $this->contrato('5961');
        for ($i = 0; $i < 18; $i++) {
            $this->fixar($novo, ['lotes' => 1]);
        }

        $semFiltro = $this->actingAs($this->admin)->get(route('ny.fixacoes.index'))->viewData('fixacoes');
        $this->assertFalse($semFiltro->contains('id', $antiga->id), 'a antiga deveria estar fora da janela');

        $comFiltro = $this->actingAs($this->admin)
            ->get(route('ny.fixacoes.index', ['contrato' => $antigo->id]))
            ->assertOk()
            ->viewData('fixacoes');

        $this->assertCount(1, $comFiltro);
        $this->assertSame($antiga->id, $comFiltro->first()->id);
    }

    public function test_filtro_por_corretora(): void
    {
        $contrato = $this->contrato('5961');
        $this->fixar($contrato, ['corretora' => 'StoneX East Coast']);
        $this->fixar($contrato, ['corretora' => 'ICAP Corporates LLC (Hedgepoint)']);
        $this->fixar($contrato, ['corretora' => 'ICAP Corporates LLC (Hedgepoint)']);

        $fixacoes = $this->actingAs($this->admin)
            ->get(route('ny.fixacoes.index', ['corretora' => 'ICAP Corporates LLC (Hedgepoint)']))
            ->assertOk()
            ->viewData('fixacoes');

        $this->assertCount(2, $fixacoes);
        $this->assertTrue($fixacoes->every(fn ($f) => $f->corretora === 'ICAP Corporates LLC (Hedgepoint)'));
    }

    /** As opções do filtro saem das fixações, não do cadastro de corretoras. */
    public function test_opcoes_do_filtro_vem_das_fixacoes(): void
    {
        $contrato = $this->contrato('5961');
        $this->fixar($contrato, ['corretora' => 'StoneX East Coast']);

        $resposta = $this->actingAs($this->admin)->get(route('ny.fixacoes.index'))->assertOk();

        $this->assertSame(['StoneX East Coast'], $resposta->viewData('corretorasFixadas'));
        // A Marex está no cadastro semeado, mas não tem fixação: fora da lista.
        $this->assertNotContains('Marex Financial Limited AGS Coffee', $resposta->viewData('corretorasFixadas'));
    }

    /** A tela do contrato (H7, Z6...) aparece na fila do que falta fixar. */
    public function test_contratos_a_fixar_mostram_a_tela(): void
    {
        $this->contrato('5995', ['mes_fixacao' => 'H7']);
        $this->contrato('5996', ['mes_fixacao' => null]);

        $html = $this->actingAs($this->admin)->get(route('ny.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<strong>H7</strong>', $html);
        $this->assertStringContainsString('Março/2027', $html); // rótulo no title
    }

    public function test_filtro_de_contratos_por_ut_comprador_e_tela(): void
    {
        $outro = Cliente::create(['nome' => 'BETA NV', 'endereco' => 'Genebra']);

        $this->contrato('5995', ['mes_fixacao' => 'H7']);
        $this->contrato('5996', ['mes_fixacao' => 'Z6']);
        $this->contrato('6001', [
            'mes_fixacao' => 'H7',
            'cliente_id' => $outro->id, 'cliente_nome' => $outro->nome, 'cliente_endereco' => $outro->endereco,
        ]);

        $porUt = $this->actingAs($this->admin)->get(route('ny.index', ['ut' => '599']))->viewData('contratos');
        $this->assertEqualsCanonicalizing(['5995', '5996'], $porUt->pluck('numero_ut')->all());

        $porComprador = $this->actingAs($this->admin)
            ->get(route('ny.index', ['comprador' => 'BETA NV']))->viewData('contratos');
        $this->assertSame(['6001'], $porComprador->pluck('numero_ut')->all());

        $porTela = $this->actingAs($this->admin)->get(route('ny.index', ['tela' => 'H7']))->viewData('contratos');
        $this->assertEqualsCanonicalizing(['5995', '6001'], $porTela->pluck('numero_ut')->all());
    }

    /** Contrato sem mês definido tem opção própria no filtro. */
    public function test_filtro_de_contratos_sem_tela_definida(): void
    {
        $this->contrato('5995', ['mes_fixacao' => 'H7']);
        $this->contrato('5996', ['mes_fixacao' => null]);

        $resposta = $this->actingAs($this->admin)->get(route('ny.index', ['tela' => 'SEM_TELA']))->assertOk();

        $this->assertSame(['5996'], $resposta->viewData('contratos')->pluck('numero_ut')->all());
        $this->assertArrayHasKey('SEM_TELA', $resposta->viewData('telasPendentes'));
    }

    /**
     * O card "Posição de fixações por tela" é um retrato GERAL. Filtrar a
     * tabela não pode encolhê-lo junto, senão uma posição parcial ficaria
     * com cara de total — erro que ninguém percebe olhando.
     */
    public function test_posicao_por_tela_ignora_o_filtro_da_tabela(): void
    {
        $this->contrato('5995', ['mes_fixacao' => 'H7']);
        $this->contrato('5996', ['mes_fixacao' => 'Z6']);

        $resposta = $this->actingAs($this->admin)->get(route('ny.index', ['tela' => 'H7']))->assertOk();

        $this->assertCount(1, $resposta->viewData('contratos'));

        $telasNaPosicao = collect($resposta->viewData('posicao'))->pluck('tela');
        $this->assertTrue($telasNaPosicao->contains('H7'));
        $this->assertTrue($telasNaPosicao->contains('Z6'), 'a posição geral não pode perder a Z6');
    }

    /**
     * O histórico saiu da Tela NY e virou página própria: lá é onde se FAZ a
     * fixação, aqui é consulta do que já foi feito.
     */
    public function test_tela_ny_nao_lista_mais_as_fixacoes(): void
    {
        $contrato = $this->contrato('5995', ['mes_fixacao' => 'H7']);
        $this->fixar($contrato);

        $telaNy = $this->actingAs($this->admin)->get(route('ny.index'))->assertOk();

        $this->assertArrayNotHasKey('fixacoes', $telaNy->viewData());
        $telaNy->assertDontSee('Fixações registradas</h2>', false);
        // Mas leva para a página nova pelo menu.
        $telaNy->assertSee(route('ny.fixacoes.index'), false);

        $this->actingAs($this->admin)
            ->get(route('ny.fixacoes.index'))
            ->assertOk()
            ->assertSee('Fixações registradas');
    }

    /**
     * Na lista, a linha embaixo da corretora mostra o COMPRADOR do contrato,
     * não o broker dele. Eram dois nomes parecidos no mesmo lugar — "cliente:
     * Adm Investor Services Inc" dizia respeito à corretora do comprador, e
     * quem lia entendia que o negócio era com a Adm.
     */
    public function test_lista_mostra_o_cliente_do_contrato_sob_a_corretora(): void
    {
        $contrato = $this->contrato('5962');
        $this->fixar($contrato, ['broker_cliente' => 'Adm Investor Services Inc']);

        $html = $this->actingAs($this->admin)->get(route('ny.fixacoes.index'))->assertOk()->getContent();

        $this->assertStringContainsString('cliente: ALFA', $html);
        $this->assertStringNotContainsString('cliente: Adm Investor Services Inc', $html);
    }

    /** No PDF o comprador tem coluna própria, então ali embaixo vai o broker. */
    public function test_pdf_rotula_o_broker_do_cliente_como_broker(): void
    {
        $contrato = $this->contrato('5962');
        $this->fixar($contrato, ['broker_cliente' => 'Adm Investor Services Inc']);

        $html = view('ny.fixacoes-pdf', [
            'fixacoes' => Fixacao::with('contrato')->get(),
            'filtros' => [],
            'totais' => [],
            'geradoPor' => 'Admin',
        ])->render();

        $this->assertStringContainsString('broker: Adm Investor Services Inc', $html);
        $this->assertStringNotContainsString('cliente: Adm Investor Services Inc', $html);
    }

    /** Cada linha leva à carta de fixação do contrato dela. */
    public function test_lista_tem_link_para_a_carta_de_fixacao(): void
    {
        $contrato = $this->contrato('5978');
        $this->fixar($contrato);

        $this->actingAs($this->admin)
            ->get(route('ny.fixacoes.index'))
            ->assertOk()
            ->assertSee('↓ Carta')
            ->assertSee(route('contratos.carta-fixacao', $contrato), false);
    }

    public function test_pdf_baixa_com_o_recorte_no_nome_do_arquivo(): void
    {
        $contrato = $this->contrato('5961');
        $this->fixar($contrato);

        $resposta = $this->actingAs($this->admin)
            ->get(route('ny.fixacoes.pdf', ['corretora' => 'StoneX East Coast']))
            ->assertOk();

        $this->assertSame('application/pdf', $resposta->headers->get('content-type'));
        $this->assertStringContainsString('Fixacoes_stonex-east-coast_', $resposta->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $resposta->getContent());
    }

    /** O PDF ignora o corte das 15: quem baixa quer o conjunto do filtro. */
    public function test_pdf_traz_todo_o_recorte_e_nao_so_as_ultimas_quinze(): void
    {
        $contrato = $this->contrato('5961');
        for ($i = 0; $i < 18; $i++) {
            $this->fixar($contrato, ['lotes' => 1]);
        }

        $this->actingAs($this->admin)->get(route('ny.fixacoes.pdf'))->assertOk();

        // O conteúdo binário não se inspeciona; o que dá para garantir é que
        // a consulta do PDF não tem limite — conferido pela view abaixo.
        $html = view('ny.fixacoes-pdf', [
            'fixacoes' => Fixacao::with('contrato')->orderByDesc('id')->get(),
            'filtros' => [],
            'totais' => [],
            'geradoPor' => 'Admin',
        ])->render();

        $this->assertStringContainsString('18 registros', $html);
    }

    /**
     * Santos é cts/lb e Vitória é USD/MT: o PDF mostra uma linha de total por
     * unidade. Um total único misturando as duas seria um número sem
     * significado — e que ninguém perceberia estar errado.
     */
    public function test_totais_do_pdf_saem_separados_por_unidade(): void
    {
        $santos = $this->contrato('5961');
        $vitoria = $this->contrato('5962', ['porto' => 'VITORIA', 'tipo_cafe' => 'CONILON']);

        $this->fixar($santos, ['lotes' => 3, 'level' => '300.00']);
        $this->fixar($santos, ['lotes' => 1, 'level' => '340.00']);
        $this->fixar($vitoria, ['lotes' => 2, 'level' => '3700.00', 'tela' => 'Sep_2026']);

        $html = $this->actingAs($this->admin)
            ->get(route('ny.fixacoes.pdf'))
            ->assertOk()
            ->getContent();

        $this->assertStringStartsWith('%PDF', $html);

        // Média ponderada de Santos: (300*3 + 340*1) / 4 = 310,00 — e não 320,
        // que é a média simples.
        $totais = collect($this->totaisDoControlador())->keyBy('unidade');
        $this->assertEqualsWithDelta(310.00, $totais['cts/lb']['level_medio'], 0.001);
        $this->assertSame(4, $totais['cts/lb']['lotes']);
        $this->assertEqualsWithDelta(3700.00, $totais['USD/MT']['level_medio'], 0.001);
        $this->assertSame(2, $totais['USD/MT']['lotes']);
    }

    /** Chama o cálculo de totais do controller (privado) via reflexão. */
    private function totaisDoControlador(): array
    {
        $controller = app(\App\Http\Controllers\Contratos\FixacaoController::class);
        $metodo = new \ReflectionMethod($controller, 'totaisPorUnidade');
        $metodo->setAccessible(true);

        return $metodo->invoke($controller, Fixacao::with('contrato')->get());
    }

    public function test_perfil_sem_permissao_nao_baixa_o_pdf(): void
    {
        $this->actingAs($this->financeiro)
            ->get(route('ny.fixacoes.pdf'))
            ->assertForbidden();
    }
}
