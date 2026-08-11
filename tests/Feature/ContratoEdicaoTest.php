<?php

namespace Tests\Feature;

use App\Models\AuditLog;
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
 * Edição, cancelamento e exclusão de contrato.
 *
 * CANCELAR e EXCLUIR são coisas diferentes de propósito: cancelar guarda o
 * registro (com motivo) e tira o contrato da posição; excluir é só para
 * lançamento errado e é bloqueado quando já existem fixações.
 */
class ContratoEdicaoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $financeiro;
    private Cliente $cliente;
    private Cliente $outroCliente;
    private Qualidade $qualidade;
    private Contrato $contrato;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Role::create(['slug' => 'admin', 'nome' => 'Administrador']);
        $financeiro = Role::create(['slug' => 'financeiro', 'nome' => 'Financeiro']);

        $this->admin = User::create([
            'role_id' => $admin->id, 'name' => 'Admin', 'email' => 'admin@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);
        $this->financeiro = User::create([
            'role_id' => $financeiro->id, 'name' => 'Fin', 'email' => 'fin@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);

        $this->cliente = Cliente::create(['nome' => 'ICONA', 'endereco' => 'Madrid – Spain']);
        $this->outroCliente = Cliente::create(['nome' => 'MIORI CF LLC', 'endereco' => 'New York – USA']);
        $this->qualidade = Qualidade::create(['descricao' => 'CAFÉ ARÁB NAT BRASIL 14/16']);

        // 108.000 kg => 1.800 sacas => 6 lotes.
        $this->contrato = Contrato::create([
            'numero_ut' => '5940', 'data_contrato' => '2026-08-05',
            'cliente_id' => $this->cliente->id, 'cliente_nome' => $this->cliente->nome,
            'cliente_endereco' => $this->cliente->endereco,
            'qualidade_id' => $this->qualidade->id, 'qualidade_descricao' => $this->qualidade->descricao,
            'tipo_cafe' => 'ARABICA', 'certificado' => 'RFA', 'quantidade_kg' => 108000,
            'tipo_container' => '40', 'embalagem' => 'Bulk Liner', 'incoterms' => 'FOB', 'porto' => 'SANTOS',
            'diferencial' => '-16.00', 'mes_fixacao' => 'Z6', 'created_by' => $this->admin->id,
        ]);
    }

    /** Payload completo do formulário de contrato, com sobrescritas. */
    private function dados(array $overrides = []): array
    {
        return array_merge([
            'numero_ut' => '5940', 'data_contrato' => '2026-08-05',
            'cliente_id' => $this->cliente->id, 'qualidade_id' => $this->qualidade->id,
            'tipo_cafe' => 'ARABICA', 'certificado' => 'RFA', 'quantidade_kg' => 108000,
            'tipo_container' => '40', 'embalagem' => 'Bulk Liner', 'incoterms' => 'FOB', 'porto' => 'SANTOS',
            'diferencial' => '-16.00', 'mes_fixacao' => 'Z6',
        ], $overrides);
    }

    private function fixarLotes(int $lotes): Fixacao
    {
        $fixacao = Fixacao::create([
            'contrato_id' => $this->contrato->id, 'corretora' => 'StoneX East Coast',
            'tela' => 'Z6', 'lotes' => $lotes, 'level' => 335, 'diferencial' => -16,
        ]);
        $this->contrato->recalcularFixacao();
        $this->contrato->refresh();

        return $fixacao;
    }

    /* ---------- Edição ---------- */

    public function test_editar_recalcula_sacas_lotes_e_containers(): void
    {
        $this->actingAs($this->admin)
            ->put(route('contratos.update', $this->contrato), $this->dados(['quantidade_kg' => 54000]))
            ->assertRedirect(route('contratos.show', $this->contrato));

        $this->contrato->refresh();
        $this->assertEqualsWithDelta(900, (float) $this->contrato->sacas, 0.01); // 54000 / 60
        $this->assertSame(3, $this->contrato->lotes);                             // round(900 / 283,49)
        $this->assertSame(3, $this->contrato->containers);                        // ceil(54000 / 25000)
    }

    public function test_editar_regrava_o_snapshot_do_cliente_e_da_qualidade(): void
    {
        $novaQualidade = Qualidade::create(['descricao' => 'NY 2/3 SCREEN 17/18 SS FINE CUP']);

        $this->actingAs($this->admin)->put(route('contratos.update', $this->contrato), $this->dados([
            'cliente_id' => $this->outroCliente->id,
            'qualidade_id' => $novaQualidade->id,
        ]));

        $this->contrato->refresh();
        $this->assertSame('MIORI CF LLC', $this->contrato->cliente_nome);
        $this->assertSame('New York – USA', $this->contrato->cliente_endereco);
        $this->assertSame('NY 2/3 SCREEN 17/18 SS FINE CUP', $this->contrato->qualidade_descricao);
    }

    public function test_editar_nao_deixa_reduzir_abaixo_dos_lotes_ja_fixados(): void
    {
        $this->fixarLotes(4); // 4 dos 6 lotes já fixados

        // 54.000 kg dariam só 3 lotes — menos do que os 4 já fixados.
        $this->actingAs($this->admin)
            ->put(route('contratos.update', $this->contrato), $this->dados(['quantidade_kg' => 54000]))
            ->assertSessionHasErrors('quantidade_kg');

        $this->contrato->refresh();
        $this->assertSame(6, $this->contrato->lotes); // nada mudou
    }

    public function test_editar_aceita_reduzir_ate_o_total_ja_fixado(): void
    {
        $this->fixarLotes(3);

        // 54.000 kg => 3 lotes, exatamente o que já está fixado: pode.
        $this->actingAs($this->admin)
            ->put(route('contratos.update', $this->contrato), $this->dados(['quantidade_kg' => 54000]))
            ->assertSessionHasNoErrors();

        $this->contrato->refresh();
        $this->assertSame(3, $this->contrato->lotes);
        // Ficou completo => vira FIXED com o preço da tranche (335 - 16).
        $this->assertTrue($this->contrato->fixado);
        $this->assertEqualsWithDelta(319.00, (float) $this->contrato->preco_fixado, 0.01);
    }

    public function test_editar_nao_zera_contrato_marcado_como_fixed_na_mao(): void
    {
        // Sem nenhuma tranche: o FIXED manual do formulário deve sobreviver.
        $this->actingAs($this->admin)->put(route('contratos.update', $this->contrato), $this->dados([
            'fixado' => '1', 'preco_fixado' => '353.40', 'preco_fixado_unidade' => 'CTS_LB',
        ]))->assertSessionHasNoErrors();

        $this->contrato->refresh();
        $this->assertTrue($this->contrato->fixado);
        $this->assertEqualsWithDelta(353.40, (float) $this->contrato->preco_fixado, 0.01);
        $this->assertNull($this->contrato->diferencial); // limpa o modo não usado
    }

    public function test_numero_ut_continua_unico_mas_ignora_o_proprio_contrato(): void
    {
        Contrato::create([
            'numero_ut' => '5941', 'data_contrato' => '2026-08-05',
            'cliente_id' => $this->cliente->id, 'cliente_nome' => $this->cliente->nome,
            'cliente_endereco' => $this->cliente->endereco,
            'qualidade_id' => $this->qualidade->id, 'qualidade_descricao' => $this->qualidade->descricao,
            'tipo_cafe' => 'ARABICA', 'certificado' => 'RFA', 'quantidade_kg' => 54000,
            'tipo_container' => '40', 'embalagem' => 'Bulk Liner', 'incoterms' => 'FOB', 'porto' => 'SANTOS',
            'created_by' => $this->admin->id,
        ]);

        // Salvar mantendo o próprio número: pode.
        $this->actingAs($this->admin)
            ->put(route('contratos.update', $this->contrato), $this->dados())
            ->assertSessionHasNoErrors();

        // Usar o número de outro contrato: não pode.
        $this->actingAs($this->admin)
            ->put(route('contratos.update', $this->contrato), $this->dados(['numero_ut' => '5941']))
            ->assertSessionHasErrors(['numero_ut' => 'Já existe um contrato com este número UT.']);
    }

    public function test_tela_de_edicao_abre_com_os_dados_do_contrato(): void
    {
        $this->actingAs($this->admin)->get(route('contratos.edit', $this->contrato))
            ->assertOk()
            ->assertSee('value="5940"', false)
            ->assertSee('108000', false);
    }

    /* ---------- Cancelamento ---------- */

    public function test_cancelar_exige_motivo(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('contratos.cancelar', $this->contrato), ['motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->assertFalse($this->contrato->fresh()->cancelado());
    }

    public function test_cancelar_guarda_motivo_autor_e_data(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('contratos.cancelar', $this->contrato), ['motivo' => 'Cliente desistiu do embarque de setembro.'])
            ->assertRedirect(route('contratos.show', $this->contrato));

        $this->contrato->refresh();
        $this->assertTrue($this->contrato->cancelado());
        $this->assertSame('Cliente desistiu do embarque de setembro.', $this->contrato->motivo_cancelamento);
        $this->assertSame($this->admin->id, $this->contrato->cancelado_por);
        $this->assertNotNull($this->contrato->cancelado_em);

        $this->assertTrue(AuditLog::where('acao', 'contrato_cancelado')->exists());
    }

    public function test_contrato_cancelado_sai_da_tela_ny_e_dos_numeros(): void
    {
        $painelAntes = (new \App\Services\PainelInicial)->numeros();
        $this->assertSame(1, $painelAntes['contratos_a_fixar']);
        $this->assertSame(6, $painelAntes['lotes_a_fixar']);

        $this->actingAs($this->admin)
            ->patch(route('contratos.cancelar', $this->contrato), ['motivo' => 'Cliente desistiu.']);

        $painelDepois = (new \App\Services\PainelInicial)->numeros();
        $this->assertSame(0, $painelDepois['contratos_a_fixar']);
        $this->assertSame(0, $painelDepois['lotes_a_fixar']);
        $this->assertEqualsWithDelta(0.0, $painelDepois['sacas_contratadas'], 0.01);

        // Na Tela NY ele não é mais listado como contrato a fixar. (Não dá
        // para usar assertDontSee('UT 5940'): o flash do cancelamento cita
        // o número do contrato na própria página.)
        $resposta = $this->actingAs($this->admin)->get(route('ny.index'))->assertOk();
        $this->assertCount(0, $resposta->viewData('contratos'));
        $resposta->assertSee('Nenhum contrato pendente de fixação');
    }

    public function test_contrato_cancelado_nao_pode_ser_fixado_nem_editado(): void
    {
        $this->contrato->update(['cancelado_em' => now(), 'motivo_cancelamento' => 'x', 'cancelado_por' => $this->admin->id]);

        $this->actingAs($this->admin)->post(route('ny.fixacoes.store'), [
            'contratos' => [$this->contrato->id], 'corretora' => 'StoneX East Coast',
            'tela' => 'Z6', 'lotes' => 1, 'level' => '335.00',
            'diferenciais' => [$this->contrato->id => '-16.00'],
        ])->assertSessionHasErrors('contratos');

        $this->actingAs($this->admin)
            ->put(route('contratos.update', $this->contrato), $this->dados())
            ->assertSessionHasErrors('numero_ut');
    }

    public function test_contrato_cancelado_aparece_na_lista_com_badge_e_motivo(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('contratos.cancelar', $this->contrato), ['motivo' => 'Cliente desistiu do embarque.']);

        $this->actingAs($this->admin)->get(route('contratos.index'))
            ->assertOk()
            ->assertSee('UT 5940')
            ->assertSee('CANCELADO');

        $this->actingAs($this->admin)->get(route('contratos.show', $this->contrato))
            ->assertOk()
            ->assertSee('Contrato cancelado em')
            ->assertSee('Cliente desistiu do embarque.');
    }

    public function test_pdf_do_contrato_cancelado_continua_disponivel(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('contratos.cancelar', $this->contrato), ['motivo' => 'Cliente desistiu.']);

        $this->actingAs($this->admin)->get(route('contratos.pdf', $this->contrato))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /* ---------- Reativação (cancelamento por engano) ---------- */

    public function test_reativar_exige_motivo(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('contratos.cancelar', $this->contrato), ['motivo' => 'Cancelado por engano.']);

        $this->actingAs($this->admin)
            ->patch(route('contratos.reativar', $this->contrato), ['motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->assertTrue($this->contrato->fresh()->cancelado());
    }

    public function test_reativar_devolve_o_contrato_para_a_posicao(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('contratos.cancelar', $this->contrato), ['motivo' => 'Cancelado por engano.']);
        $this->assertSame(0, (new \App\Services\PainelInicial)->numeros()['contratos_a_fixar']);

        $this->actingAs($this->admin)
            ->patch(route('contratos.reativar', $this->contrato), ['motivo' => 'Cancelamento lançado no contrato errado.'])
            ->assertRedirect(route('contratos.show', $this->contrato));

        $this->contrato->refresh();
        $this->assertFalse($this->contrato->cancelado());
        $this->assertNull($this->contrato->motivo_cancelamento);
        $this->assertNull($this->contrato->cancelado_por);

        // Volta a contar na posição e a aparecer na Tela NY.
        $numeros = (new \App\Services\PainelInicial)->numeros();
        $this->assertSame(1, $numeros['contratos_a_fixar']);
        $this->assertSame(6, $numeros['lotes_a_fixar']);
        $this->assertCount(1, $this->actingAs($this->admin)->get(route('ny.index'))->viewData('contratos'));
    }

    public function test_reativar_guarda_no_log_o_motivo_e_o_cancelamento_anterior(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('contratos.cancelar', $this->contrato), ['motivo' => 'Cliente desistiu do embarque.']);
        $this->actingAs($this->admin)
            ->patch(route('contratos.reativar', $this->contrato), ['motivo' => 'Era o contrato errado.']);

        $log = AuditLog::where('acao', 'contrato_reativado')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Era o contrato errado.', $log->descricao);
        // A história do cancelamento não se perde ao limpar o registro.
        $this->assertStringContainsString('Cliente desistiu do embarque.', $log->descricao);
    }

    public function test_reativar_contrato_ja_ativo_nao_faz_nada(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('contratos.reativar', $this->contrato), ['motivo' => 'Tentativa em contrato ativo.'])
            ->assertSessionHas('status', 'Este contrato já estava ativo.');

        $this->assertFalse($this->contrato->fresh()->cancelado());
    }

    public function test_tela_do_contrato_cancelado_oferece_reativacao(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('contratos.cancelar', $this->contrato), ['motivo' => 'Cliente desistiu.']);

        $this->actingAs($this->admin)->get(route('contratos.show', $this->contrato))
            ->assertOk()
            ->assertSee('Foi cancelado por engano? Reativar contrato')
            ->assertSee('Motivo da reativação');
    }

    /* ---------- Exclusão ---------- */

    public function test_excluir_exige_motivo_e_registra_no_audit_log(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('contratos.destroy', $this->contrato), ['motivo' => ''])
            ->assertSessionHasErrors('motivo');
        $this->assertNotNull(Contrato::find($this->contrato->id));

        $this->actingAs($this->admin)
            ->delete(route('contratos.destroy', $this->contrato), ['motivo' => 'Lançado em duplicidade.'])
            ->assertRedirect(route('contratos.index'));

        $this->assertNull(Contrato::find($this->contrato->id));

        // O registro sumiu: o motivo só sobrevive no log.
        $log = AuditLog::where('acao', 'contrato_excluido')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Lançado em duplicidade.', $log->descricao);
    }

    public function test_excluir_e_bloqueado_quando_ja_existe_fixacao(): void
    {
        $this->fixarLotes(2);

        $this->actingAs($this->admin)
            ->delete(route('contratos.destroy', $this->contrato), ['motivo' => 'Quero apagar mesmo assim.'])
            ->assertSessionHasErrors('motivo');

        // Nem o contrato nem a fixação foram apagados.
        $this->assertNotNull(Contrato::find($this->contrato->id));
        $this->assertSame(1, Fixacao::count());
    }

    public function test_tela_do_contrato_bloqueia_o_botao_excluir_quando_ha_fixacao(): void
    {
        $this->fixarLotes(2);

        $this->actingAs($this->admin)->get(route('contratos.show', $this->contrato))
            ->assertOk()
            ->assertSee('Bloqueado:')
            ->assertSee('Use "Cancelar contrato".', false);
    }

    /* ---------- Permissões ---------- */

    public function test_perfil_sem_permissao_nao_edita_cancela_nem_exclui(): void
    {
        $this->actingAs($this->financeiro)->get(route('contratos.edit', $this->contrato))->assertForbidden();
        $this->actingAs($this->financeiro)->put(route('contratos.update', $this->contrato), $this->dados())->assertForbidden();
        $this->actingAs($this->financeiro)->patch(route('contratos.cancelar', $this->contrato), ['motivo' => 'x'])->assertForbidden();
        $this->actingAs($this->financeiro)->patch(route('contratos.reativar', $this->contrato), ['motivo' => 'x'])->assertForbidden();
        $this->actingAs($this->financeiro)->delete(route('contratos.destroy', $this->contrato), ['motivo' => 'x'])->assertForbidden();
    }
}
