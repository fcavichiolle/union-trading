<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Embarque;
use App\Models\Qualidade;
use App\Models\Role;
use App\Models\User;
use App\Services\PosicaoVendas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Embarques: a agenda das estufagens e o lado "vendido" da posição.
 *
 * O que estes testes guardam: na planilha a venda só saía da soma quando
 * alguém apagava a linha à mão. Aqui ela sai na BAIXA do estoque — não na
 * nota: faturar e dar baixa são coisas diferentes, e o café faturado sem
 * baixa ainda está no armazém. O que ainda nem virou embarque continua
 * contando, pelo mês do contrato.
 */
class EmbarqueTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $financeiro;
    private Cliente $cliente;
    private Qualidade $qualidade;

    protected function setUp(): void
    {
        parent::setUp();

        // Tempo congelado: a grade da posição começa no mês corrente, e sem
        // isso o teste passaria hoje e quebraria na virada do mês.
        Carbon::setTestNow('2026-11-17 10:00:00');

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
        $this->qualidade = Qualidade::create(['descricao' => 'NY 3/4 SCREEN 14/16 GOOD CUP']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** 38.400 kg = 640 sacas, standard 14/16 GC, embarque em dezembro/2026. */
    private function contrato(string $ut, array $overrides = []): Contrato
    {
        return Contrato::create(array_merge([
            'numero_ut' => $ut, 'data_contrato' => '2026-08-05',
            'cliente_id' => $this->cliente->id, 'cliente_nome' => $this->cliente->nome,
            'cliente_endereco' => $this->cliente->endereco,
            'qualidade_id' => $this->qualidade->id, 'qualidade_descricao' => $this->qualidade->descricao,
            'standard' => '1416_GC', 'tipo_cafe' => 'ARABICA', 'certificado' => 'RFA',
            'quantidade_kg' => 38400, 'tipo_container' => '20', 'embalagem' => 'Bulk Liner',
            'incoterms' => 'FOB', 'porto' => 'SANTOS', 'embarque_mes' => '2026-12-01',
            'created_by' => $this->admin->id,
        ], $overrides));
    }

    private function embarque(Contrato $contrato, array $overrides = []): Embarque
    {
        $e = new Embarque(array_merge([
            'mes_faturamento' => '2026-12-01', 'quantidade_kg' => 19200, 'status' => 'BOOKING',
        ], $overrides));
        $e->contrato()->associate($contrato);
        $e->save();

        return $e;
    }

    private function posicao(): array
    {
        return app(PosicaoVendas::class)->calcular();
    }

    public function test_sacas_sao_calculadas_no_servidor(): void
    {
        $e = $this->embarque($this->contrato('5973'));
        $this->assertEqualsWithDelta(320, (float) $e->sacas, 0.01); // 19200 / 60

        // Saca de 59 kg quando a embalagem do contrato é "Jute Bags 59kg".
        $juta = $this->embarque($this->contrato('5974', ['embalagem' => 'Jute Bags 59kg']), ['quantidade_kg' => 5900]);
        $this->assertEqualsWithDelta(100, (float) $juta->sacas, 0.01);
    }

    /** O mês de faturamento é um MÊS: qualquer dia vira o dia 01. */
    public function test_mes_de_faturamento_e_normalizado_para_o_dia_primeiro(): void
    {
        $e = $this->embarque($this->contrato('5973'), ['mes_faturamento' => '2026-12-17']);

        $this->assertSame('2026-12-01', $e->fresh()->mes_faturamento->format('Y-m-d'));
    }

    /** A regra central: embarque aberto pesa na posição, no mês DELE. */
    public function test_embarque_em_aberto_entra_na_posicao_no_mes_de_faturamento(): void
    {
        $contrato = $this->contrato('5973');
        $this->embarque($contrato, ['quantidade_kg' => 38400, 'mes_faturamento' => '2027-01-01']);

        $linha = $this->posicao()['linhas']['1416_GC'];

        // O contrato dizia dezembro, mas a parcela rolou para janeiro: vale janeiro.
        $this->assertEqualsWithDelta(640, $linha['valores']['2027-01'], 0.01);
        $this->assertEqualsWithDelta(0, $linha['valores']['2026-12'], 0.01);
    }

    /**
     * Faturar e dar baixa são coisas diferentes: a nota não tira o café do
     * armazém, então a venda continua na posição até a baixa do estoque.
     */
    public function test_faturar_nao_tira_da_posicao_quem_tira_e_a_baixa(): void
    {
        $contrato = $this->contrato('5973');
        $e = $this->embarque($contrato, ['quantidade_kg' => 38400]);

        $this->assertEqualsWithDelta(640, $this->posicao()['total'], 0.01);

        $this->actingAs($this->admin)
            ->patch(route('embarques.faturar', $e))
            ->assertRedirect();

        $e->refresh();
        $this->assertSame('FATURADO', $e->status);
        $this->assertSame('2026-11-17', $e->faturado_em->format('Y-m-d'));
        $this->assertTrue($e->aguardandoBaixa());
        $this->assertEqualsWithDelta(640, $this->posicao()['total'], 0.01);

        $this->actingAs($this->admin)
            ->patch(route('embarques.baixar', $e), ['baixado_em' => '2026-11-16'])
            ->assertRedirect();

        $e->refresh();
        $this->assertSame('2026-11-16', $e->baixado_em->format('Y-m-d'));
        $this->assertSame('FATURADO', $e->status); // a baixa não mexe na etapa
        $this->assertFalse($e->aguardandoBaixa());
        $this->assertEqualsWithDelta(0, $this->posicao()['total'], 0.01);
        $this->assertDatabaseHas('audit_logs', ['acao' => 'embarque_baixado']);
    }

    /** Sem data, a baixa é de hoje; data no futuro não existe. */
    public function test_baixa_sem_data_usa_hoje_e_recusa_data_futura(): void
    {
        $e = $this->embarque($this->contrato('5973'), ['status' => 'FATURADO']);

        $this->actingAs($this->admin)
            ->patch(route('embarques.baixar', $e), ['baixado_em' => '2026-11-18'])
            ->assertSessionHasErrors('baixado_em');
        $this->assertNull($e->fresh()->baixado_em);

        $this->actingAs($this->admin)->patch(route('embarques.baixar', $e))->assertRedirect();
        $this->assertSame('2026-11-17', $e->fresh()->baixado_em->format('Y-m-d'));
    }

    /** Baixa dada por engano: apagar a data devolve a venda à posição. */
    public function test_desfazer_a_baixa_devolve_a_venda_a_posicao(): void
    {
        $e = $this->embarque($this->contrato('5973'), [
            'quantidade_kg' => 38400, 'status' => 'FATURADO', 'baixado_em' => '2026-11-10',
        ]);
        $this->assertEqualsWithDelta(0, $this->posicao()['total'], 0.01);

        $this->actingAs($this->admin)->put(route('embarques.update', $e), [
            'mes_faturamento' => '2026-12', 'quantidade_kg' => 38400, 'status' => 'FATURADO',
            'faturado_em' => '2026-11-09', 'baixado_em' => '',
        ])->assertRedirect();

        $this->assertNull($e->fresh()->baixado_em);
        $this->assertEqualsWithDelta(640, $this->posicao()['total'], 0.01);
    }

    /** A fila de baixas aparece sempre, independente do filtro da agenda. */
    public function test_controle_logistico_mostra_a_fila_de_faturados_sem_baixa(): void
    {
        $contrato = $this->contrato('5973', ['quantidade_kg' => 57600]);
        $this->embarque($contrato, ['sufixo' => 'A']);
        $this->embarque($contrato, ['sufixo' => 'B', 'status' => 'FATURADO']);
        $this->embarque($contrato, ['sufixo' => 'C', 'status' => 'FATURADO', 'baixado_em' => '2026-11-10']);

        $tela = $this->actingAs($this->admin)->get(route('embarques.index'))->assertOk();

        $tela->assertSee('Faturados aguardando baixa de estoque');
        $this->assertSame(['B'], $tela->viewData('aguardandoBaixa')->pluck('sufixo')->all());

        $semBaixa = $this->actingAs($this->admin)->get(route('embarques.index', ['status' => 'aguardando_baixa']));
        $this->assertSame(1, $semBaixa->viewData('total'));

        $baixados = $this->actingAs($this->admin)->get(route('embarques.index', ['status' => 'baixados']));
        $this->assertSame(1, $baixados->viewData('total'));
        $baixados->assertSee('baixa 10/11/2026');
    }

    /** Desfazer o faturamento (voltar a etapa) apaga a data da nota. */
    public function test_voltar_a_etapa_apaga_a_data_do_faturamento(): void
    {
        $e = $this->embarque($this->contrato('5973'), ['status' => 'FATURADO', 'faturado_em' => '2026-11-10']);
        $this->assertSame('2026-11-10', $e->fresh()->faturado_em->format('Y-m-d'));

        $e->update(['status' => 'BOOKING']);

        $this->assertNull($e->fresh()->faturado_em);
    }

    /**
     * Venda recém-lançada, sem embarque nenhum, tem de aparecer na posição —
     * pelo mês de embarque do contrato. E só o que SOBRA do contrato entra
     * assim: o que já virou embarque conta pelo embarque.
     */
    public function test_saldo_sem_embarque_entra_pelo_mes_do_contrato(): void
    {
        $contrato = $this->contrato('5973'); // 640 sc, dezembro

        $this->assertEqualsWithDelta(640, $this->posicao()['linhas']['1416_GC']['valores']['2026-12'], 0.01);

        // Metade vira embarque de janeiro: sobram 320 em dezembro.
        $this->embarque($contrato, ['mes_faturamento' => '2027-01-01']);
        $linha = $this->posicao()['linhas']['1416_GC'];

        $this->assertEqualsWithDelta(320, $linha['valores']['2026-12'], 0.01);
        $this->assertEqualsWithDelta(320, $linha['valores']['2027-01'], 0.01);
        $this->assertEqualsWithDelta(640, $linha['total'], 0.01);
    }

    /** Sobra de menos de uma saca é arredondamento de peso, não venda. */
    public function test_sobra_menor_que_uma_saca_nao_entra(): void
    {
        $contrato = $this->contrato('5973');
        // Embarque já baixado: só sobram os 50 kg do contrato.
        $this->embarque($contrato, ['quantidade_kg' => 38350, 'status' => 'FATURADO', 'baixado_em' => '2026-11-10']);

        $this->assertEqualsWithDelta(0, $this->posicao()['total'], 0.01);
    }

    public function test_contrato_cancelado_nao_entra_na_posicao(): void
    {
        $this->contrato('5973', ['cancelado_em' => now(), 'motivo_cancelamento' => 'cliente desistiu']);

        $this->assertEqualsWithDelta(0, $this->posicao()['total'], 0.01);
    }

    /** Nada some em silêncio: sem standard, a venda ganha linha própria e aviso. */
    public function test_contrato_sem_standard_aparece_em_linha_propria_com_aviso(): void
    {
        $this->contrato('5973', ['standard' => null]);

        $posicao = $this->posicao();

        $this->assertEqualsWithDelta(640, $posicao['linhas'][PosicaoVendas::SEM_STANDARD]['total'], 0.01);
        $this->assertStringContainsString('UT 5973', implode(' ', $posicao['avisos']));
    }

    /** Embarque de mês que já passou e não foi faturado vira "Atrasado". */
    public function test_embarque_aberto_de_mes_passado_vai_para_atrasado(): void
    {
        $this->embarque($this->contrato('5973'), ['quantidade_kg' => 38400, 'mes_faturamento' => '2026-10-01']);

        $posicao = $this->posicao();

        $this->assertArrayHasKey(PosicaoVendas::ANTERIORES, $posicao['colunas']);
        $this->assertEqualsWithDelta(640, $posicao['linhas']['1416_GC']['valores'][PosicaoVendas::ANTERIORES], 0.01);
    }

    /** Coluna extra só existe quando tem volume. */
    public function test_grade_tem_doze_meses_a_partir_do_mes_corrente(): void
    {
        $colunas = array_keys($this->posicao()['colunas']);

        $this->assertCount(12, $colunas);
        $this->assertSame('2026-11', $colunas[0]);
        $this->assertSame('2027-10', $colunas[11]);
    }

    public function test_agenda_mostra_so_os_abertos_por_padrao(): void
    {
        $contrato = $this->contrato('5973', ['quantidade_kg' => 57600]);
        $this->embarque($contrato, ['sufixo' => 'A']);
        $this->embarque($contrato, ['sufixo' => 'B', 'status' => 'FATURADO', 'baixado_em' => '2026-11-10']);

        $abertos = $this->actingAs($this->admin)->get(route('embarques.index'))->assertOk();
        $this->assertSame(1, $abertos->viewData('total'));
        $abertos->assertSee('UT 5973 A')->assertDontSee('UT 5973 B');

        $todos = $this->actingAs($this->admin)->get(route('embarques.index', ['status' => 'todos']))->assertOk();
        $this->assertSame(2, $todos->viewData('total'));
    }

    public function test_agenda_filtra_por_mes_e_busca(): void
    {
        $this->embarque($this->contrato('5973'), ['mes_faturamento' => '2026-12-01']);
        $this->embarque($this->contrato('6001'), ['mes_faturamento' => '2027-01-01']);

        $porMes = $this->actingAs($this->admin)->get(route('embarques.index', ['mes' => '2027-01']));
        $this->assertSame(1, $porMes->viewData('total'));

        $porBusca = $this->actingAs($this->admin)->get(route('embarques.index', ['busca' => '5973']));
        $this->assertSame(1, $porBusca->viewData('total'));
        $porBusca->assertSee('UT 5973');
    }

    /** Contrato sem logística aparece na agenda, senão ninguém programa. */
    public function test_agenda_lista_contratos_com_volume_a_programar(): void
    {
        $this->contrato('5973');

        $this->actingAs($this->admin)
            ->get(route('embarques.index'))
            ->assertOk()
            ->assertSee('Contratos com volume a programar')
            ->assertSee('UT 5973');
    }

    /** O formulário já nasce com o que falta do contrato e o mês dele. */
    public function test_novo_embarque_ja_vem_com_o_saldo_do_contrato(): void
    {
        $contrato = $this->contrato('5973');
        $this->embarque($contrato); // 19.200 de 38.400

        $resposta = $this->actingAs($this->admin)->get(route('embarques.create', $contrato))->assertOk();

        $this->assertEqualsWithDelta(19200, (float) $resposta->viewData('embarque')->quantidade_kg, 0.01);
        $this->assertSame('2026-12', $resposta->viewData('embarque')->mes_faturamento->format('Y-m'));
    }

    public function test_lanca_embarque_pelo_formulario(): void
    {
        $contrato = $this->contrato('5973');

        $this->actingAs($this->admin)
            ->post(route('embarques.store', $contrato), [
                'sufixo' => 'A', 'mes_faturamento' => '2026-12', 'quantidade_kg' => '19200',
                'status' => 'AG_BOOKING', 'pss' => 'A_ENVIAR', 'data_estufagem' => '2026-12-09',
                'destino' => 'HAMBURG', 'navio' => 'MAERSK LA PAZ', 'booking' => '280836851 - MAERSK',
            ])
            ->assertRedirect(route('contratos.show', $contrato));

        $e = Embarque::first();
        $this->assertSame('UT 5973 A', $e->referencia());
        $this->assertSame('2026-12-01', $e->mes_faturamento->format('Y-m-d'));
        $this->assertEqualsWithDelta(320, (float) $e->sacas, 0.01);
        $this->assertSame('2026-12-09', $e->data_estufagem->format('Y-m-d'));
        $this->assertNull($e->faturado_em);
    }

    public function test_validacao_do_formulario(): void
    {
        $contrato = $this->contrato('5973');

        $this->actingAs($this->admin)
            ->post(route('embarques.store', $contrato), ['mes_faturamento' => '', 'quantidade_kg' => '0', 'status' => 'XX'])
            ->assertSessionHasErrors(['mes_faturamento', 'quantidade_kg', 'status']);

        $this->assertSame(0, Embarque::count());
    }

    /** A agenda é leitura para todo perfil; mexer é de quem opera contratos. */
    public function test_financeiro_ve_a_agenda_mas_nao_altera(): void
    {
        $contrato = $this->contrato('5973');
        $e = $this->embarque($contrato);

        $this->actingAs($this->financeiro)->get(route('embarques.index'))
            ->assertOk()
            ->assertSee('UT 5973')
            ->assertDontSee('Faturar</button>', false);

        $this->actingAs($this->financeiro)->get(route('embarques.create', $contrato))->assertForbidden();
        $this->actingAs($this->financeiro)->patch(route('embarques.faturar', $e))->assertForbidden();
        $this->actingAs($this->financeiro)->patch(route('embarques.baixar', $e))->assertForbidden();
        $this->actingAs($this->financeiro)->delete(route('embarques.destroy', $e))->assertForbidden();

        $this->assertSame('BOOKING', $e->fresh()->status);
    }

    /** A cascata apagaria a logística junto — então excluir é bloqueado. */
    public function test_contrato_com_embarque_nao_pode_ser_excluido(): void
    {
        $contrato = $this->contrato('5973');
        $this->embarque($contrato);

        $this->actingAs($this->admin)
            ->from(route('contratos.show', $contrato))
            ->delete(route('contratos.destroy', $contrato), ['motivo' => 'lançado errado'])
            ->assertSessionHasErrors('motivo');

        $this->assertNotNull(Contrato::find($contrato->id));
        $this->assertSame(1, Embarque::count());
    }

    public function test_excluir_embarque_devolve_o_volume_ao_contrato(): void
    {
        $contrato = $this->contrato('5973');
        $e = $this->embarque($contrato, ['status' => 'FATURADO', 'quantidade_kg' => 38400, 'baixado_em' => '2026-11-10']);
        $this->assertEqualsWithDelta(0, $this->posicao()['total'], 0.01);

        $this->actingAs($this->admin)->delete(route('embarques.destroy', $e))->assertRedirect();

        $this->assertEqualsWithDelta(640, $this->posicao()['total'], 0.01);
    }

    /** Tela do contrato: os embarques e o saldo a programar. */
    public function test_tela_do_contrato_mostra_embarques_e_saldo(): void
    {
        $contrato = $this->contrato('5973');
        $this->embarque($contrato, ['sufixo' => 'A']);

        $this->actingAs($this->admin)
            ->get(route('contratos.show', $contrato))
            ->assertOk()
            ->assertSee('UT 5973 A')
            ->assertSee('14/16 GC')
            ->assertSee('19.200 kg', false);
    }

    /** Embarques acima do contrato: a tela avisa, não proíbe. */
    public function test_embarques_acima_do_contrato_geram_aviso(): void
    {
        $contrato = $this->contrato('5973');
        $this->embarque($contrato, ['quantidade_kg' => 40000]);

        $this->assertEqualsWithDelta(-1600, $contrato->fresh('embarques')->kgAProgramar(), 0.01);

        $this->actingAs($this->admin)
            ->get(route('contratos.show', $contrato))
            ->assertOk()
            ->assertSee('a mais', false);
    }

    /** Estufagem vencida ou a três dias ganha etiqueta na agenda. */
    public function test_agenda_destaca_estufagem_proxima_e_atrasada(): void
    {
        $contrato = $this->contrato('5973', ['quantidade_kg' => 57600]);
        $atrasada = $this->embarque($contrato, ['sufixo' => 'A', 'data_estufagem' => '2026-11-14']);
        $proxima = $this->embarque($contrato, ['sufixo' => 'B', 'data_estufagem' => '2026-11-19']);
        $longe = $this->embarque($contrato, ['sufixo' => 'C', 'data_estufagem' => '2026-12-20']);

        $this->assertSame(-3, $atrasada->diasAteEstufagem());
        $this->assertSame(2, $proxima->diasAteEstufagem());
        $this->assertSame(33, $longe->diasAteEstufagem());

        $this->actingAs($this->admin)->get(route('embarques.index'))
            ->assertOk()
            ->assertSee('atrasada 3d')
            ->assertSee('em 2d');
    }

    /**
     * Agendamentos (área de Exportação): os embarques em aberto pela data
     * da estufagem. Faturado sai; sem data vai para a lista "a agendar".
     */
    public function test_agendamentos_ordena_por_dia_e_separa_o_que_falta_agendar(): void
    {
        $contrato = $this->contrato('5973', ['quantidade_kg' => 76800]);
        $this->embarque($contrato, ['sufixo' => 'A', 'data_estufagem' => '2026-11-19']);
        $this->embarque($contrato, ['sufixo' => 'B', 'data_estufagem' => '2026-11-14']);
        $this->embarque($contrato, ['sufixo' => 'C']);                                             // sem data
        $this->embarque($contrato, ['sufixo' => 'D', 'data_estufagem' => '2026-11-10', 'status' => 'FATURADO']);

        $resposta = $this->actingAs($this->financeiro)->get(route('embarques.agenda'))->assertOk();

        // Dias em ordem, e o faturado (dia 10) não aparece.
        $this->assertSame(['2026-11-14', '2026-11-19'], $resposta->viewData('porDia')->keys()->all());
        $this->assertSame(['C'], $resposta->viewData('semData')->pluck('sufixo')->all());

        $resposta->assertSee('Sem data de estufagem')
            ->assertSee('atrasada 3d')
            ->assertDontSee('UT 5973 D');
    }

    /** O menu tem a área de Exportação, com as duas vistas. */
    public function test_menu_tem_a_area_de_exportacao(): void
    {
        $this->actingAs($this->financeiro)->get(route('embarques.agenda'))
            ->assertOk()
            ->assertSee('Exportação')
            ->assertSee(route('embarques.agenda'), false)
            ->assertSee(route('embarques.index'), false);
    }

    public function test_standard_do_contrato_e_validado(): void
    {
        $contrato = $this->contrato('5973');

        $this->assertSame('14/16 GC', $contrato->standardLabel());
        $this->assertNull($this->contrato('5974', ['standard' => null])->standardLabel());
        $this->assertArrayHasKey('GRINDERS_12UP', Contrato::standards());
    }
}
