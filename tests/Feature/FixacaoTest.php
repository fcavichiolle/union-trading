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

class FixacaoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $financeiro;
    private Cliente $cliente;
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
        $this->qualidade = Qualidade::create(['descricao' => 'CAFÉ ARÁB NAT BRASIL 14/16']);

        // 108.000 kg arábica => 1.800 sacas => 6 lotes, porto Santos (NY).
        $this->contrato = $this->novoContrato('5940', 108000);
    }

    private function novoContrato(string $ut, int $kg, array $overrides = []): Contrato
    {
        return Contrato::create(array_merge([
            'numero_ut' => $ut, 'data_contrato' => '2026-08-05',
            'cliente_id' => $this->cliente->id, 'cliente_nome' => $this->cliente->nome, 'cliente_endereco' => $this->cliente->endereco,
            'qualidade_id' => $this->qualidade->id, 'qualidade_descricao' => $this->qualidade->descricao,
            'tipo_cafe' => 'ARABICA', 'certificado' => 'RFA', 'quantidade_kg' => $kg,
            'tipo_container' => '40', 'embalagem' => 'Bulk Liner', 'incoterms' => 'FOB', 'porto' => 'SANTOS',
            'diferencial' => '-16.00', 'created_by' => $this->admin->id,
        ], $overrides));
    }

    /** POST no formato do formulário: 1 contrato por padrão, tela Z6. */
    private function fixar(array $overrides = [])
    {
        $payload = array_merge([
            'contratos' => [$this->contrato->id],
            'corretora' => 'STONEX',
            'tela' => 'Z6',
            'lotes' => 2,
            'level' => '335.00',
            'diferenciais' => [$this->contrato->id => '-16.00'],
        ], $overrides);

        return $this->actingAs($this->admin)->post(route('ny.fixacoes.store'), $payload);
    }

    public function test_preco_da_tranche_e_level_mais_diferencial_calculado_no_servidor(): void
    {
        $this->fixar()->assertRedirect(route('ny.index'));

        $f = Fixacao::first();
        $this->assertEqualsWithDelta(319.00, (float) $f->preco, 0.001); // 335 - 16
        $this->assertSame(2, $f->lotes);
        $this->assertSame('Z6', $f->tela);
    }

    public function test_fixacao_parcial_nao_vira_fixed(): void
    {
        $this->fixar(['lotes' => 2]);

        $this->contrato->refresh();
        $this->assertFalse($this->contrato->fixado);
        $this->assertTrue($this->contrato->parcialmenteFixado());
        $this->assertSame(4, $this->contrato->lotesRestantes());
    }

    public function test_completar_os_lotes_vira_fixed_com_media_ponderada(): void
    {
        $this->fixar(['lotes' => 2, 'level' => '335.00', 'diferenciais' => [$this->contrato->id => '-16.00']]); // 2 @ 319,00
        $this->fixar(['lotes' => 4, 'level' => '340.00', 'diferenciais' => [$this->contrato->id => '-10.00']]); // 4 @ 330,00

        $this->contrato->refresh();
        $this->assertTrue($this->contrato->fixado);
        // (2*319 + 4*330) / 6 = 1958/6 = 326,33
        $this->assertEqualsWithDelta(326.33, (float) $this->contrato->preco_fixado, 0.01);
        $this->assertSame('CTS_LB', $this->contrato->preco_fixado_unidade); // unidade da bolsa (Santos = NY)
    }

    public function test_fixacao_em_grupo_fixa_todos_os_contratos_de_uma_vez(): void
    {
        // 54.000 kg => 900 sacas => 3 lotes, mesmo cliente/bolsa.
        $contrato2 = $this->novoContrato('5941', 54000, ['diferencial' => '-10.00']);

        $this->fixar([
            'contratos' => [$this->contrato->id, $contrato2->id],
            'level' => '335.00',
            'tela' => 'Z6',
            'diferenciais' => [
                $this->contrato->id => '-16.00',
                $contrato2->id => '-10.00',
            ],
            // sem 'lotes': no modo grupo fixa todos os restantes
            'lotes' => null,
        ])->assertSessionHasNoErrors();

        $this->contrato->refresh();
        $contrato2->refresh();

        $this->assertTrue($this->contrato->fixado);
        $this->assertTrue($contrato2->fixado);
        $this->assertEqualsWithDelta(319.00, (float) $this->contrato->preco_fixado, 0.01); // 335 - 16
        $this->assertEqualsWithDelta(325.00, (float) $contrato2->preco_fixado, 0.01);      // 335 - 10

        // Uma tranche por contrato, cada uma com todos os lotes restantes e a mesma tela.
        $this->assertSame(2, Fixacao::count());
        $this->assertSame(6, Fixacao::where('contrato_id', $this->contrato->id)->first()->lotes);
        $this->assertSame(3, Fixacao::where('contrato_id', $contrato2->id)->first()->lotes);
        $this->assertSame(['Z6'], Fixacao::query()->pluck('tela')->unique()->values()->all());
    }

    public function test_grupo_com_bolsas_diferentes_e_recusado(): void
    {
        $vitoria = $this->novoContrato('5942', 54000, ['porto' => 'VITORIA']);

        $this->fixar([
            'contratos' => [$this->contrato->id, $vitoria->id],
            'diferenciais' => [$this->contrato->id => '-16.00', $vitoria->id => '-85'],
            'lotes' => null,
        ])->assertSessionHasErrors('contratos');

        $this->assertSame(0, Fixacao::count());
    }

    public function test_tela_de_outra_bolsa_e_recusada(): void
    {
        // Contrato de Santos (NY) com tela de Londres.
        $this->fixar(['tela' => 'Sep_2026'])->assertSessionHasErrors('tela');
    }

    public function test_diferencial_faltando_no_grupo_e_recusado(): void
    {
        $contrato2 = $this->novoContrato('5941', 54000);

        $this->fixar([
            'contratos' => [$this->contrato->id, $contrato2->id],
            'diferenciais' => [$this->contrato->id => '-16.00'], // faltou o do 5941
            'lotes' => null,
        ])->assertSessionHasErrors('diferenciais');
    }

    public function test_nao_deixa_fixar_mais_lotes_do_que_restam(): void
    {
        $this->fixar(['lotes' => 5]); // restam 1

        $this->fixar(['lotes' => 2])->assertSessionHasErrors('lotes');
        $this->assertSame(1, Fixacao::count()); // só a primeira tranche existe
    }

    public function test_contrato_ja_fixado_nao_aceita_nova_fixacao(): void
    {
        $this->fixar(['lotes' => 6]); // fixa tudo

        $this->fixar(['lotes' => 1])->assertSessionHasErrors('contratos');
    }

    public function test_excluir_tranche_reverte_fixed_para_a_fixar(): void
    {
        $this->fixar(['lotes' => 6]);
        $this->contrato->refresh();
        $this->assertTrue($this->contrato->fixado);

        $f = Fixacao::first();
        $this->actingAs($this->admin)->delete(route('ny.fixacoes.destroy', $f))->assertRedirect(route('ny.index'));

        $this->contrato->refresh();
        $this->assertFalse($this->contrato->fixado);
        $this->assertNull($this->contrato->preco_fixado);
        $this->assertSame(6, $this->contrato->lotesRestantes());
    }

    public function test_corretora_fora_da_lista_e_recusada(): void
    {
        $this->fixar(['corretora' => 'BANCO_DO_ZE'])->assertSessionHasErrors('corretora');
    }

    public function test_broker_do_cliente_e_opcional_e_fica_gravado(): void
    {
        // Sem broker do cliente: salva normalmente.
        $this->fixar(['lotes' => 1])->assertSessionHasNoErrors();
        $this->assertNull(Fixacao::first()->broker_cliente);

        // Com broker do cliente: fica gravado.
        $this->fixar(['lotes' => 1, 'broker_cliente' => 'MACQUARIE_USA'])->assertSessionHasNoErrors();
        $f = Fixacao::orderByDesc('id')->first();
        $this->assertSame('MACQUARIE_USA', $f->broker_cliente);
        $this->assertSame('Macquarie USA', $f->brokerClienteLabel());
    }

    public function test_broker_do_cliente_fora_da_lista_e_recusado(): void
    {
        $this->fixar(['broker_cliente' => 'CORRETORA_FANTASMA'])->assertSessionHasErrors('broker_cliente');
    }

    public function test_porto_vitoria_fixa_em_usd_mt(): void
    {
        $this->contrato->update(['porto' => 'VITORIA']);

        $this->fixar([
            'lotes' => 6, 'level' => '3810', 'tela' => 'Sep_2026',
            'diferenciais' => [$this->contrato->id => '-85'],
        ]);

        $this->contrato->refresh();
        $this->assertTrue($this->contrato->fixado);
        $this->assertEqualsWithDelta(3725.00, (float) $this->contrato->preco_fixado, 0.01);
        $this->assertSame('USD_MT', $this->contrato->preco_fixado_unidade);
        $this->assertSame('Sep_2026', Fixacao::first()->tela);
    }

    public function test_perfil_sem_permissao_recebe_403(): void
    {
        $this->actingAs($this->financeiro)->get(route('ny.index'))->assertForbidden();
        $this->actingAs($this->financeiro)->post(route('ny.fixacoes.store'), [])->assertForbidden();
    }

    public function test_tela_ny_lista_contrato_pendente(): void
    {
        $this->actingAs($this->admin)->get(route('ny.index'))
            ->assertOk()
            ->assertSee('UT 5940')
            ->assertSee('A FIXAR');
    }
}
