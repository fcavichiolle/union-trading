<?php

namespace Tests\Feature;

use App\Models\Classificacao;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Contrato;
use App\Models\FinanceiroCompra;
use App\Models\Fornecedor;
use App\Models\Qualidade;
use App\Models\Role;
use App\Models\User;
use App\Services\PainelInicial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PainelInicialTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $diretoria;
    private Fornecedor $fornecedor;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Role::create(['slug' => 'admin', 'nome' => 'Administrador']);
        $diretoria = Role::create(['slug' => 'diretoria', 'nome' => 'Diretoria']);

        $this->admin = User::create([
            'role_id' => $admin->id, 'name' => 'Admin', 'email' => 'admin@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);
        $this->diretoria = User::create([
            'role_id' => $diretoria->id, 'name' => 'Dir', 'email' => 'dir@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);

        $this->fornecedor = Fornecedor::create(['nome' => 'LUIZ PEREIRA', 'cnpj' => '12345678000199']);
    }

    private function novaCompra(array $overrides = []): Compra
    {
        return Compra::create(array_merge([
            'uts' => 'UTS 100', 'mes_ano' => '2026-08-01', 'fornecedor_id' => $this->fornecedor->id,
            'armazem' => 'SAAG', 'certificacao' => 'RFA', 'tipo_entrada' => 'BICA',
            'volume_sacas' => 600, 'created_by' => $this->admin->id,
        ], $overrides));
    }

    private function novoContrato(string $ut, array $overrides = []): Contrato
    {
        $cliente = Cliente::firstOrCreate(['nome' => 'ICONA'], ['endereco' => 'Madrid']);
        $qualidade = Qualidade::firstOrCreate(['descricao' => 'CAFÉ 14/16']);

        return Contrato::create(array_merge([
            'numero_ut' => $ut, 'data_contrato' => '2026-08-05',
            'cliente_id' => $cliente->id, 'cliente_nome' => $cliente->nome, 'cliente_endereco' => $cliente->endereco,
            'qualidade_id' => $qualidade->id, 'qualidade_descricao' => $qualidade->descricao,
            'tipo_cafe' => 'ARABICA', 'certificado' => 'RFA', 'quantidade_kg' => 108000, // => 6 lotes
            'tipo_container' => '40', 'embalagem' => 'Bulk Liner', 'incoterms' => 'FOB', 'porto' => 'SANTOS',
            'embarque_mes' => '2026-09-01', 'created_by' => $this->admin->id,
        ], $overrides));
    }

    /** Compra completa: com lote, classificação e financeiro — não é pendência. */
    private function compraCompleta(): Compra
    {
        $compra = $this->novaCompra(['uts' => 'UTS OK', 'numero_lote' => 'L-1']);

        Classificacao::create([
            'compra_id' => $compra->id, 'padrao_final' => 'FINE_CUP',
            'peneira_1718_pct' => 100, 'peneira_1718_sacas' => 600,
            'peneira_1416_pct' => 0, 'peneira_1416_sacas' => 0,
            'mercado_interno_pct' => 0, 'mercado_interno_sacas' => 0,
            'grinders_pct' => 0, 'grinders_sacas' => 0,
            'moka_pct' => 0, 'moka_sacas' => 0,
            'created_by' => $this->admin->id,
        ]);
        FinanceiroCompra::create([
            'compra_id' => $compra->id, 'valor_saca' => 1200, 'corretor' => 'X', 'comissao' => 1,
            'created_by' => $this->admin->id,
        ]);

        return $compra;
    }

    public function test_conta_cada_tipo_de_pendencia(): void
    {
        $this->compraCompleta();                                  // sem pendência
        $this->novaCompra(['uts' => 'UTS SEM LOTE']);             // sem lote + sem class. + sem fin.
        $this->novaCompra(['uts' => 'UTS COM LOTE', 'numero_lote' => 'L-2']); // sem class. + sem fin.

        $n = app(PainelInicial::class)->numeros();

        $this->assertSame(1, $n['compras_sem_lote']);
        $this->assertSame(2, $n['compras_sem_classificacao']);
        $this->assertSame(2, $n['compras_sem_financeiro']);
        $this->assertSame(2, $n['compras_com_pendencia']); // distintas, não a soma
    }

    public function test_conta_contratos_a_fixar_e_lotes_de_exposicao(): void
    {
        $this->novoContrato('5940');                                  // 6 lotes a fixar
        $this->novoContrato('5941', ['quantidade_kg' => 54000]);       // 3 lotes a fixar
        $this->novoContrato('5942', ['fixado' => true, 'preco_fixado' => 320]); // já fixado

        $n = app(PainelInicial::class)->numeros();

        $this->assertSame(2, $n['contratos_a_fixar']);
        $this->assertSame(9, $n['lotes_a_fixar']);
    }

    public function test_lotes_a_fixar_desconta_fixacao_parcial(): void
    {
        $contrato = $this->novoContrato('5940'); // 6 lotes
        \App\Models\Fixacao::create([
            'contrato_id' => $contrato->id, 'corretora' => 'StoneX East Coast',
            'tela' => 'Z6', 'lotes' => 2, 'level' => 335, 'diferencial' => -16,
        ]);

        $n = app(PainelInicial::class)->numeros();

        $this->assertSame(4, $n['lotes_a_fixar']); // 6 - 2
    }

    public function test_pendencia_zerada_nao_vira_card(): void
    {
        $this->compraCompleta(); // nada pendente

        $pendencias = app(PainelInicial::class)->pendencias($this->admin);

        $this->assertSame([], $pendencias);
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tudo em dia.')
            ->assertDontSee('O que falta fazer');
    }

    public function test_home_mostra_cards_de_pendencia_com_link_filtrado(): void
    {
        $this->novaCompra(['uts' => 'UTS SEM LOTE']);

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('O que falta fazer')
            ->assertSee('sem nº do lote')
            ->assertSee(route('compras.index', ['pendencia' => 'sem_lote']), false);
    }

    public function test_diretoria_ve_posicao_mas_nao_pendencias_de_compra(): void
    {
        $this->novaCompra(['uts' => 'UTS SEM LOTE']);

        $this->assertSame([], app(PainelInicial::class)->pendencias($this->diretoria));

        $this->actingAs($this->diretoria)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Posição geral')
            ->assertDontSee('sem nº do lote');
    }

    public function test_posicao_soma_sacas_compradas_e_contratadas(): void
    {
        $this->novaCompra(['uts' => 'A', 'volume_sacas' => 600]);
        $this->novaCompra(['uts' => 'B', 'volume_sacas' => 400]);
        $this->novoContrato('5940'); // 108.000 kg / 60 = 1.800 sacas

        $n = app(PainelInicial::class)->numeros();

        $this->assertEqualsWithDelta(1000, $n['sacas_compradas'], 0.01);
        $this->assertEqualsWithDelta(1800, $n['sacas_contratadas'], 0.01);
    }

    public function test_badges_do_menu_so_aparecem_com_pendencia(): void
    {
        $painel = app(PainelInicial::class);
        $this->assertSame([], $painel->badgesMenu($this->admin));

        // Nova instância: os números são memoizados por requisição.
        $this->novaCompra(['uts' => 'UTS SEM LOTE']);
        $this->novoContrato('5940');

        $badges = (new PainelInicial)->badgesMenu($this->admin);
        $this->assertSame(1, $badges['compras.index']);
        $this->assertSame(6, $badges['ny.index']);

        // E aparecem renderizados no menu.
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('sb-badge', false);
    }

    public function test_diretoria_nao_recebe_badges(): void
    {
        $this->novaCompra(['uts' => 'UTS SEM LOTE']);

        $this->assertSame([], (new PainelInicial)->badgesMenu($this->diretoria));
    }

    public function test_filtro_de_pendencia_em_compras_lancadas(): void
    {
        $this->compraCompleta();                       // UTS OK
        $this->novaCompra(['uts' => 'UTS SEM LOTE']);  // sem lote

        $this->actingAs($this->admin)->get(route('compras.index', ['pendencia' => 'sem_lote']))
            ->assertOk()
            ->assertSee('UTS SEM LOTE')
            ->assertDontSee('UTS OK');

        $this->actingAs($this->admin)->get(route('compras.index', ['pendencia' => 'sem_financeiro']))
            ->assertOk()
            ->assertSee('UTS SEM LOTE')
            ->assertDontSee('UTS OK');

        // Sem filtro, as duas aparecem.
        $this->actingAs($this->admin)->get(route('compras.index'))
            ->assertOk()
            ->assertSee('UTS SEM LOTE')
            ->assertSee('UTS OK');
    }
}
