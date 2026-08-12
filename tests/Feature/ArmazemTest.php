<?php

namespace Tests\Feature;

use App\Models\Armazem;
use App\Models\Compra;
use App\Models\Fornecedor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Armazéns deixaram de ser ENUM no código e viraram cadastro (ago/2026).
 *
 * A diferença em relação às corretoras: a entrega aponta para o CADASTRO e
 * não guarda o nome como snapshot — renomear deve atualizar o histórico,
 * porque é o mesmo galpão com nome novo. Em troca, excluir armazém em uso é
 * bloqueado.
 */
class ArmazemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $compras;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Role::create(['slug' => 'admin', 'nome' => 'Administrador']);
        $compras = Role::create(['slug' => 'compras', 'nome' => 'Compras']);

        $this->admin = User::create([
            'role_id' => $admin->id, 'name' => 'Admin', 'email' => 'admin@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);
        $this->compras = User::create([
            'role_id' => $compras->id, 'name' => 'Compras', 'email' => 'compras@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);
    }

    /** A migration semeia os três que existiam no ENUM. */
    public function test_os_tres_armazens_antigos_viraram_cadastro(): void
    {
        $this->assertSame(
            ['DÍNAMO MACHADO', 'QUALITÉ', 'SAAG'],
            Armazem::orderBy('nome')->pluck('nome')->all()
        );
    }

    public function test_cadastra_armazem_com_cnpj_opcional(): void
    {
        $this->actingAs($this->admin)->post(route('admin.armazens.store'), [
            'nome' => 'ARMAZÉM SÃO JUDAS',
            'cidade' => 'Varginha',
            'estado' => 'MG',
        ])->assertRedirect(route('admin.armazens.index'))->assertSessionHasNoErrors();

        $armazem = Armazem::firstWhere('nome', 'ARMAZÉM SÃO JUDAS');
        $this->assertSame('Varginha', $armazem->cidade);
        $this->assertNull($armazem->documento);
    }

    public function test_cnpj_e_gravado_so_com_digitos_e_validado_quando_preenchido(): void
    {
        $this->actingAs($this->admin)->post(route('admin.armazens.store'), [
            'nome' => 'ARMAZÉM COM CNPJ', 'cidade' => 'Santos', 'estado' => 'SP',
            'documento' => '11.222.333/0001-81',
        ])->assertSessionHasNoErrors();

        $this->assertSame('11222333000181', Armazem::firstWhere('nome', 'ARMAZÉM COM CNPJ')->documento);

        // Preenchido e inválido: recusa.
        $this->actingAs($this->admin)->post(route('admin.armazens.store'), [
            'nome' => 'ARMAZÉM TORTO', 'cidade' => 'Santos', 'estado' => 'SP',
            'documento' => '11.111.111/1111-11',
        ])->assertSessionHasErrors('documento');
    }

    public function test_nome_cidade_e_estado_sao_obrigatorios(): void
    {
        $this->actingAs($this->admin)->post(route('admin.armazens.store'), [])
            ->assertSessionHasErrors([
                'nome' => 'Informe o nome do armazém.',
                'cidade' => 'Informe a cidade do armazém.',
                'estado' => 'Selecione o estado (UF).',
            ]);
    }

    public function test_nome_nao_repete(): void
    {
        $this->actingAs($this->admin)->post(route('admin.armazens.store'), [
            'nome' => 'SAAG', 'cidade' => 'Santos', 'estado' => 'SP',
        ])->assertSessionHasErrors(['nome' => 'Já existe um armazém com este nome.']);
    }

    /** Renomear acompanha o histórico — é o mesmo galpão. */
    public function test_renomear_armazem_muda_o_historico(): void
    {
        $id = $this->armazem('SAAG');
        $compra = $this->compraComEntrega($id);

        $this->actingAs($this->admin)->put(route('admin.armazens.update', $id), [
            'nome' => 'SAAG ARMAZÉNS GERAIS', 'cidade' => 'Santos', 'estado' => 'SP',
        ])->assertSessionHasNoErrors();

        $this->assertSame('SAAG ARMAZÉNS GERAIS', $compra->entregas->first()->fresh()->armazemLabel());

        // E o Estoque passa a agrupar pelo nome novo, sem partir em dois.
        $this->actingAs($this->admin)->get(route('relatorio.index'))->assertOk()
            ->assertSee('SAAG ARMAZÉNS GERAIS');
    }

    public function test_nao_exclui_armazem_em_uso(): void
    {
        $id = $this->armazem('SAAG');
        $this->compraComEntrega($id);

        $this->actingAs($this->admin)->delete(route('admin.armazens.destroy', $id))
            ->assertRedirect(route('admin.armazens.index'))
            ->assertSessionHasErrors('armazem');

        $this->assertNotNull(Armazem::find($id));
    }

    public function test_exclui_armazem_sem_uso(): void
    {
        $armazem = Armazem::create(['nome' => 'ARMAZÉM VAZIO', 'cidade' => 'Poços', 'estado' => 'MG']);

        $this->actingAs($this->admin)->delete(route('admin.armazens.destroy', $armazem))
            ->assertSessionHasNoErrors();

        $this->assertNull(Armazem::find($armazem->id));
    }

    public function test_cadastro_e_so_do_admin(): void
    {
        $this->actingAs($this->compras)->get(route('admin.armazens.index'))->assertForbidden();
        $this->actingAs($this->compras)->post(route('admin.armazens.store'), [
            'nome' => 'X', 'cidade' => 'Y', 'estado' => 'MG',
        ])->assertForbidden();
    }

    /* ---------- Armazém previsto na compra ---------- */

    public function test_compra_aceita_armazem_previsto_e_ele_e_opcional(): void
    {
        $id = $this->armazem('QUALITE');

        $this->actingAs($this->admin)->post(route('compras.store'), [
            'uts' => 'UTS 7000', 'data_compra' => '2026-08-12',
            'fornecedor_nome' => 'FAZENDA TESTE', 'certificacao' => 'RFA',
            'tipo_entrada' => 'ARABICA', 'padrao_final' => 'FINE_CUP', 'tipo_bebida' => 'DURO',
            'volume_contratado' => 300, 'armazem_id' => $id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($id, Compra::firstWhere('uts', 'UTS 7000')->armazem_id);

        // Sem armazém previsto também passa: o destino às vezes só se define
        // na hora de entregar.
        $this->actingAs($this->admin)->post(route('compras.store'), [
            'uts' => 'UTS 7001', 'data_compra' => '2026-08-12',
            'fornecedor_nome' => 'FAZENDA TESTE', 'certificacao' => 'RFA',
            'tipo_entrada' => 'ARABICA', 'padrao_final' => 'FINE_CUP', 'tipo_bebida' => 'DURO',
            'volume_contratado' => 300,
        ])->assertSessionHasNoErrors();

        $this->assertNull(Compra::firstWhere('uts', 'UTS 7001')->armazem_id);
    }

    /** O previsto vem pré-selecionado no formulário de entrega. */
    public function test_tela_da_compra_pre_seleciona_o_armazem_previsto(): void
    {
        $id = $this->armazem('DINAMO_MACHADO');
        $compra = Compra::create([
            'uts' => 'UTS 7002', 'data_compra' => '2026-08-12',
            'fornecedor_id' => Fornecedor::create(['nome' => 'F'])->id,
            'certificacao' => 'RFA', 'tipo_entrada' => 'ARABICA', 'volume_contratado' => 300,
            'armazem_id' => $id, 'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->get(route('compras.show', $compra))->assertOk()
            ->assertSee('Armazém previsto')
            ->assertSee('DÍNAMO MACHADO')
            ->assertSee('value="' . $id . '" selected', false);
    }

    public function test_entrega_exige_armazem_existente(): void
    {
        $compra = Compra::create([
            'uts' => 'UTS 7003', 'data_compra' => '2026-08-12',
            'fornecedor_id' => Fornecedor::create(['nome' => 'F'])->id,
            'certificacao' => 'RFA', 'tipo_entrada' => 'ARABICA', 'volume_contratado' => 300,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->post(route('compras.entregas.store', $compra), [
            'data_entrega' => '2026-08-12', 'armazem_id' => 99999, 'volume_sacas' => 100,
        ])->assertSessionHasErrors(['armazem_id' => 'O armazém selecionado não está mais no cadastro.']);
    }

    private function compraComEntrega(int $armazemId): Compra
    {
        $compra = Compra::create([
            'uts' => 'UTS ' . fake()->unique()->numberBetween(1000, 9999),
            'data_compra' => '2026-08-12',
            'fornecedor_id' => Fornecedor::create(['nome' => 'FAZENDA ' . fake()->unique()->word()])->id,
            'certificacao' => 'RFA', 'tipo_entrada' => 'ARABICA',
            'padrao_final' => 'FINE_CUP', 'tipo_bebida' => 'DURO',
            'volume_contratado' => 300, 'created_by' => $this->admin->id,
        ]);

        $compra->entregas()->create([
            'data_entrega' => '2026-08-12', 'armazem_id' => $armazemId,
            'volume_sacas' => 300, 'numero_lote' => 'L-1', 'created_by' => $this->admin->id,
        ]);

        \App\Models\Classificacao::create([
            'compra_id' => $compra->id, 'padrao_final' => 'FINE_CUP', 'tipo_bebida' => 'DURO',
            'peneira_1718_pct' => 100, 'peneira_1718_sacas' => 300,
            'created_by' => $this->admin->id,
        ]);

        return $compra->load('entregas');
    }
}
