<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\Entrega;
use App\Models\Fornecedor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Entregas: a mesma UTS entra no armazém em partes, em meses e armazéns
 * diferentes, cada uma com seu número de lote. É a tela do funcionário 3,
 * que confere e ajusta o volume real — para mais ou para menos.
 */
class EntregaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $diretoria;
    private Compra $compra;

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

        $fornecedor = Fornecedor::create(['nome' => 'LUIZ PEREIRA', 'documento' => '12345678000199']);
        $this->compra = Compra::create([
            'uts' => 'UTS 7312', 'data_compra' => '2026-08-10', 'fornecedor_id' => $fornecedor->id,
            'certificacao' => 'SEM_CERT', 'tipo_entrada' => 'ARABICA', 'logistica' => 'POSTO',
            'volume_contratado' => 500, 'valor_saca' => 1630, 'created_by' => $this->admin->id,
        ]);
    }

    private function lancar(array $dados = [])
    {
        return $this->actingAs($this->admin)->post(route('compras.entregas.store', $this->compra), array_merge([
            'data_entrega' => '2026-08-14', 'armazem_id' => $this->armazem('QUALITE'), 'volume_sacas' => 250, 'numero_lote' => '',
        ], $dados));
    }

    /** O caso que motivou tudo: 500 sacas, 250 hoje e 250 mês que vem. */
    public function test_a_mesma_uts_aceita_entregas_em_meses_e_armazens_diferentes(): void
    {
        $this->lancar(['data_entrega' => '2026-08-14', 'armazem_id' => $this->armazem('QUALITE'), 'volume_sacas' => 250])
            ->assertRedirect(route('compras.show', $this->compra));
        $this->lancar(['data_entrega' => '2026-09-14', 'armazem_id' => $this->armazem('SAAG'), 'volume_sacas' => 250]);

        $this->compra->refresh();
        $this->assertSame(2, $this->compra->entregas()->count());
        $this->assertEqualsWithDelta(500.0, $this->compra->sacasEntregues(), 0.01);
        $this->assertEqualsWithDelta(0.0, $this->compra->saldoAEntregar(), 0.01);
        $this->assertTrue($this->compra->totalmenteEntregue());
        $this->assertSame(['QUALITÉ', 'SAAG'], $this->compra->entregas->map(fn ($e) => $e->armazemLabel())->sort()->values()->all());
    }

    public function test_saldo_a_entregar_aparece_enquanto_falta_volume(): void
    {
        $this->lancar(['volume_sacas' => 250]);

        $this->compra->refresh();
        $this->assertEqualsWithDelta(250.0, $this->compra->saldoAEntregar(), 0.01);
        $this->assertFalse($this->compra->totalmenteEntregue());
        $this->assertTrue(Compra::comSaldoAEntregar()->where('id', $this->compra->id)->exists());
    }

    /** O funcionário 3 ajusta o que chegou de verdade: 500 viraram 480. */
    public function test_volume_pode_ficar_abaixo_do_contratado(): void
    {
        $this->lancar(['volume_sacas' => 480])->assertSessionHasNoErrors();

        $this->compra->refresh();
        $this->assertEqualsWithDelta(480.0, $this->compra->sacasEntregues(), 0.01);
        $this->assertEqualsWithDelta(20.0, $this->compra->saldoAEntregar(), 0.01);
        // Paga-se pelo que entrou.
        $this->assertEqualsWithDelta(480 * 1630, $this->compra->valorEntregue(), 0.01);
        $this->assertEqualsWithDelta(500 * 1630, $this->compra->valorContratado(), 0.01);
    }

    /** …e pode chegar mais do que o contratado, sem o sistema recusar. */
    public function test_volume_pode_ficar_acima_do_contratado(): void
    {
        $this->lancar(['volume_sacas' => 520])->assertSessionHasNoErrors();

        $this->compra->refresh();
        $this->assertTrue($this->compra->entregouAMais());
        $this->assertEqualsWithDelta(-20.0, $this->compra->saldoAEntregar(), 0.01);
        $this->assertFalse(Compra::comSaldoAEntregar()->where('id', $this->compra->id)->exists());
    }

    public function test_mensagem_diz_quanto_falta_ou_avisa_excedente(): void
    {
        $this->lancar(['volume_sacas' => 200]);
        $this->assertStringContainsString('Faltam 300,00 sc', session('status'));

        $this->lancar(['volume_sacas' => 350]);
        $this->assertStringContainsString('50,00 sc a mais', session('status'));
    }

    public function test_entrega_sem_lote_nao_entra_no_estoque_definitivo(): void
    {
        $this->lancar(['volume_sacas' => 250, 'numero_lote' => '']);
        $this->lancar(['volume_sacas' => 250, 'numero_lote' => 'L-2026-01']);

        $this->assertSame(1, Entrega::semNumeroLote()->count());
        $this->assertSame(1, Entrega::comNumeroLote()->count());
    }

    /** O funcionário 3 volta depois e informa o lote. */
    public function test_editar_entrega_para_informar_o_lote(): void
    {
        $this->lancar(['volume_sacas' => 250]);
        $entrega = Entrega::first();
        $this->assertTrue($entrega->precisaDeNumeroLote());

        $this->actingAs($this->admin)->put(route('compras.entregas.update', [$this->compra, $entrega]), [
            'data_entrega' => '2026-08-14', 'armazem_id' => $this->armazem('QUALITE'), 'volume_sacas' => 245, 'numero_lote' => 'L-2026-77',
        ])->assertRedirect(route('compras.show', $this->compra));

        $entrega->refresh();
        $this->assertSame('L-2026-77', $entrega->numero_lote);
        $this->assertEqualsWithDelta(245.0, (float) $entrega->volume_sacas, 0.01);
    }

    public function test_excluir_entrega_recalcula_o_saldo(): void
    {
        $this->lancar(['volume_sacas' => 500]);
        $entrega = Entrega::first();
        $this->assertTrue($this->compra->refresh()->totalmenteEntregue());

        $this->actingAs($this->admin)
            ->delete(route('compras.entregas.destroy', [$this->compra, $entrega]))
            ->assertRedirect(route('compras.show', $this->compra));

        $this->compra->refresh();
        $this->assertSame(0, $this->compra->entregas()->count());
        $this->assertEqualsWithDelta(500.0, $this->compra->saldoAEntregar(), 0.01);
    }

    public function test_entrega_de_outra_compra_da_404(): void
    {
        $outra = Compra::create([
            'uts' => 'UTS 9999', 'data_compra' => '2026-08-10',
            'fornecedor_id' => $this->compra->fornecedor_id,
            'certificacao' => 'SEM_CERT', 'volume_contratado' => 100, 'created_by' => $this->admin->id,
        ]);
        $this->lancar(['volume_sacas' => 250]);
        $entrega = Entrega::first();

        $this->actingAs($this->admin)
            ->delete(route('compras.entregas.destroy', [$outra, $entrega]))
            ->assertNotFound();
    }

    public function test_campos_obrigatorios_da_entrega(): void
    {
        $this->actingAs($this->admin)
            ->post(route('compras.entregas.store', $this->compra), [])
            ->assertSessionHasErrors([
                'data_entrega' => 'Informe a data da entrega.',
                'armazem_id' => 'Selecione o armazém que recebeu o café.',
                'volume_sacas' => 'Informe quantas sacas (ou quantos quilos) entraram no armazém.',
            ]);
    }

    public function test_perfil_sem_permissao_nao_lanca_entrega(): void
    {
        $this->actingAs($this->diretoria)
            ->post(route('compras.entregas.store', $this->compra), [
                'data_entrega' => '2026-08-14', 'armazem_id' => $this->armazem('SAAG'), 'volume_sacas' => 100,
            ])
            ->assertForbidden();
    }

    /* ==============================================================
     * UTS grande, entrando em muitas partes
     * ============================================================== */

    /**
     * Uma UTS de 1.000 sacas em CINCO entregas — três meses, os três
     * armazéns e SAAG recebendo duas vezes. Confere a soma parcial a cada
     * lançamento (é o que o funcionário 3 lê na tela) e o fechamento em
     * zero no fim.
     */
    public function test_uts_de_mil_sacas_dividida_em_cinco_entregas(): void
    {
        $compra = Compra::create([
            'uts' => 'UTS 8000', 'data_compra' => '2026-08-01',
            'fornecedor_id' => $this->compra->fornecedor_id,
            'certificacao' => 'RFA', 'tipo_entrada' => 'ARABICA', 'logistica' => 'POSTO',
            'volume_contratado' => 1000, 'valor_saca' => 1200, 'created_by' => $this->admin->id,
        ]);

        $partes = [
            ['data_entrega' => '2026-08-14', 'armazem_id' => $this->armazem('SAAG'), 'volume_sacas' => 200, 'numero_lote' => 'L-8001'],
            ['data_entrega' => '2026-08-14', 'armazem_id' => $this->armazem('QUALITE'), 'volume_sacas' => 250, 'numero_lote' => 'L-8002'],
            ['data_entrega' => '2026-09-14', 'armazem_id' => $this->armazem('SAAG'), 'volume_sacas' => 150, 'numero_lote' => 'L-8003'],
            ['data_entrega' => '2026-09-14', 'armazem_id' => $this->armazem('DINAMO_MACHADO'), 'volume_sacas' => 300, 'numero_lote' => 'L-8004'],
            // A última chega sem o lote: o armazém ainda não informou.
            ['data_entrega' => '2026-10-14', 'armazem_id' => $this->armazem('QUALITE'), 'volume_sacas' => 100, 'numero_lote' => ''],
        ];

        $acumulado = 0.0;

        foreach ($partes as $i => $parte) {
            $this->actingAs($this->admin)
                ->post(route('compras.entregas.store', $compra), $parte)
                ->assertRedirect(route('compras.show', $compra))
                ->assertSessionHasNoErrors();

            $acumulado += $parte['volume_sacas'];
            $compra->refresh();

            $this->assertSame($i + 1, $compra->entregas()->count());
            $this->assertEqualsWithDelta($acumulado, $compra->sacasEntregues(), 0.01);
            $this->assertEqualsWithDelta(1000 - $acumulado, $compra->saldoAEntregar(), 0.01);
        }

        // Fechou exatamente o contratado: sem saldo, sem divergência.
        $this->assertTrue($compra->totalmenteEntregue());
        $this->assertFalse($compra->divergenciaPendente());
        $this->assertFalse($compra->entregouAMais());
        $this->assertFalse(Compra::comSaldoAEntregar()->where('id', $compra->id)->exists());

        // Paga-se pelo entregue — e aqui entregue == contratado.
        $this->assertEqualsWithDelta(1000 * 1200, $compra->valorEntregue(), 0.01);
        $this->assertEqualsWithDelta(1000 * 1200, $compra->valorContratado(), 0.01);

        // A UTS continua pendente por causa da entrega sem lote (e da
        // classificação, que ainda não veio).
        $this->assertSame(1, Entrega::semNumeroLote()->where('compra_id', $compra->id)->count());
        $this->assertTrue(Compra::comPendencia()->where('id', $compra->id)->exists());

        // A tela mostra as cinco linhas, os três armazéns e o total.
        $tela = $this->actingAs($this->admin)->get(route('compras.show', $compra))->assertOk();
        foreach (['L-8001', 'L-8002', 'L-8003', 'L-8004'] as $lote) {
            $tela->assertSee($lote);
        }
        $tela->assertSee('SAAG')
            ->assertSee('QUALITÉ')
            ->assertSee('DÍNAMO MACHADO')
            ->assertSee('Total entregue')
            ->assertSee('1.000,00')
            ->assertSee('1 entrega sem número de lote');
    }

    /**
     * Mesma UTS partida em cinco, com a classificação da UTS inteira: o
     * Estoque rateia as peneiras pelo volume de cada entrega e ignora a
     * parte sem lote. Duas entregas no mesmo armazém somam numa linha só.
     */
    public function test_estoque_rateia_as_cinco_entregas_por_armazem(): void
    {
        $compra = Compra::create([
            'uts' => 'UTS 8010', 'data_compra' => '2026-08-01',
            'fornecedor_id' => $this->compra->fornecedor_id,
            'certificacao' => 'RFA', 'volume_contratado' => 1000, 'created_by' => $this->admin->id,
        ]);

        foreach ([
            ['2026-08', 'SAAG', 200, 'L-1'],
            ['2026-08', 'QUALITE', 250, 'L-2'],
            ['2026-09', 'SAAG', 150, 'L-3'],
            ['2026-09', 'DINAMO_MACHADO', 300, 'L-4'],
            ['2026-10', 'QUALITE', 100, null], // sem lote: fora do estoque
        ] as [$mes, $armazem, $sacas, $lote]) {
            $compra->entregas()->create([
                'data_entrega' => $mes . '-01', 'armazem_id' => $this->armazem($armazem), 'volume_sacas' => $sacas,
                'numero_lote' => $lote, 'created_by' => $this->admin->id,
            ]);
        }

        // Classificação é da UTS inteira: 1.000 sacas, tudo na 17/18.
        \App\Models\Classificacao::create([
            'compra_id' => $compra->id, 'padrao_final' => 'FINE_CUP', 'tipo_bebida' => 'DURO',
            'peneira_1718_pct' => 100, 'peneira_1718_sacas' => 1000,
            'peneira_1416_pct' => 0, 'peneira_1416_sacas' => 0,
            'mercado_interno_pct' => 0, 'mercado_interno_sacas' => 0,
            'grinders_pct' => 0, 'grinders_sacas' => 0,
            'moka_pct' => 0, 'moka_sacas' => 0,
            'created_by' => $this->admin->id,
        ]);

        $resposta = $this->actingAs($this->admin)->get(route('relatorio.index'))->assertOk();
        $linhas = $resposta->viewData('linhas')->keyBy('armazem');

        // SAAG recebeu duas vezes (200 + 150) e vira uma linha de 350.
        $this->assertEqualsWithDelta(350.0, (float) $linhas['SAAG']->peneira_1718, 0.01);
        $this->assertEqualsWithDelta(250.0, (float) $linhas['QUALITÉ']->peneira_1718, 0.01);
        $this->assertEqualsWithDelta(300.0, (float) $linhas['DÍNAMO MACHADO']->peneira_1718, 0.01);

        // 900 em estoque; as 100 sem lote ficam de fora — mas avisadas.
        $this->assertEqualsWithDelta(900.0, $resposta->viewData('totalGeral'), 0.01);
        $this->assertEqualsWithDelta(100.0, $resposta->viewData('pendentes')['aguardando_sacas'], 0.01);
        $resposta->assertSee('Fora do estoque:')->assertSee('100,00 sc');
    }

    /**
     * A quinta entrega vem curta e não vem mais nada: liquidar encerra a
     * UTS com as 980 que entraram, sem apagar o contratado.
     */
    public function test_liquidar_uts_partida_em_cinco_com_quebra_na_ultima(): void
    {
        $compra = Compra::create([
            'uts' => 'UTS 8020', 'data_compra' => '2026-08-01',
            'fornecedor_id' => $this->compra->fornecedor_id,
            'certificacao' => 'RFA', 'volume_contratado' => 1000,
            'valor_saca' => 1200, 'created_by' => $this->admin->id,
        ]);

        foreach ([200, 250, 150, 300, 80] as $i => $sacas) {
            $this->actingAs($this->admin)->post(route('compras.entregas.store', $compra), [
                'data_entrega' => '2026-08-14', 'armazem_id' => $this->armazem('SAAG'),
                'volume_sacas' => $sacas, 'numero_lote' => 'L-80' . $i,
            ])->assertSessionHasNoErrors();
        }

        $compra->refresh();
        $this->assertEqualsWithDelta(980.0, $compra->sacasEntregues(), 0.01);
        $this->assertTrue($compra->divergenciaPendente());
        $this->assertTrue($compra->podeLiquidar());

        $this->actingAs($this->admin)->patch(route('compras.liquidar', $compra))
            ->assertRedirect(route('compras.show', $compra));

        $compra->refresh();
        $this->assertTrue($compra->liquidada());
        $this->assertFalse($compra->divergenciaPendente());
        // O contratado continua registrado; o reconhecido é o que entrou.
        $this->assertEqualsWithDelta(1000.0, (float) $compra->volume_contratado, 0.01);
        $this->assertEqualsWithDelta(980.0, $compra->volumeReconhecido(), 0.01);
        $this->assertEqualsWithDelta(980 * 1200, $compra->valorEntregue(), 0.01);
        $this->assertFalse(Compra::comSaldoAEntregar()->where('id', $compra->id)->exists());
    }

    public function test_tela_da_compra_mostra_contratado_entregue_e_saldo(): void
    {
        $this->lancar(['volume_sacas' => 250, 'armazem_id' => $this->armazem('QUALITE')]);

        $this->actingAs($this->admin)->get(route('compras.show', $this->compra))
            ->assertOk()
            ->assertSee('Contratado')
            ->assertSee('Entregue')
            ->assertSee('Falta entregar')
            ->assertSee('250,00 sc')
            ->assertSee('QUALITÉ')
            ->assertSee('sem número de lote');
    }
}
