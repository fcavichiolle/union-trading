<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Fornecedor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Guarda contra o bug de tradução faltando: o `.env` usa APP_LOCALE=pt_BR
 * com fallback também pt_BR, então se `lang/pt_BR/validation.php` não
 * existir (ou faltar uma chave) o Laravel imprime a CHAVE crua na tela —
 * o usuário via "validation.required" em vez da mensagem.
 *
 * Os testes checam a tela renderizada (e não só a sessão), porque é ali
 * que o problema aparecia para quem usa o sistema.
 */
class MensagensValidacaoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['slug' => 'admin', 'nome' => 'Administrador']);
        $this->admin = User::create([
            'role_id' => $role->id, 'name' => 'Admin', 'email' => 'admin@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);
    }

    /** Regras que aparecem nos formulários do sistema e produzem mensagem. */
    public static function regrasUsadas(): array
    {
        return array_map(fn ($r) => [$r], [
            'required', 'required_if', 'string', 'numeric', 'integer', 'boolean',
            'array', 'distinct', 'date', 'email', 'in', 'exists', 'unique', 'confirmed',
            'current_password', 'regex', 'min', 'max', 'size', 'between', 'password',
        ]);
    }

    #[DataProvider('regrasUsadas')]
    public function test_cada_regra_de_validacao_tem_traducao(string $regra): void
    {
        $this->assertTrue(
            Lang::has("validation.{$regra}"),
            "Falta a tradução de 'validation.{$regra}' em lang/pt_BR/validation.php — "
                . 'sem ela o usuário vê a chave crua na tela.'
        );
    }

    public function test_arquivos_de_traducao_do_locale_existem(): void
    {
        // Fallback também é pt_BR: não há rede de segurança em inglês.
        $this->assertTrue(Lang::has('validation.required'));
        $this->assertTrue(Lang::has('validation.attributes.volume_sacas'));
        $this->assertTrue(Lang::has('pagination.previous'));
        $this->assertTrue(Lang::has('passwords.token'));
        $this->assertTrue(Lang::has('auth.throttle'));
    }

    public function test_nova_compra_em_branco_explica_cada_campo(): void
    {
        $this->actingAs($this->admin)
            ->from(route('compras.create'))
            ->post(route('compras.store'), [])
            ->assertSessionHasErrors([
                'uts' => 'Informe a UTS (referência da compra).',
                'data_compra' => 'Informe a data da compra.',
                'fornecedor_nome' => 'Informe o nome do vendedor (se ainda não souber o documento, deixe o CNPJ/CPF em branco).',
                'certificacao' => 'Selecione a certificação.',
                'volume_contratado' => 'Informe o volume contratado, em sacas.',
            ]);

        // E o que o usuário realmente vê ao ser devolvido ao formulário.
        // followingRedirects() renderiza o destino no mesmo ciclo — o flash
        // dos erros não sobrevive a uma requisição separada nos testes.
        $this->actingAs($this->admin)
            ->from(route('compras.create'))
            ->followingRedirects()
            ->post(route('compras.store'), [])
            ->assertOk()
            ->assertSee('5 campos precisam de atenção')
            ->assertSee('Informe a UTS (referência da compra).')
            ->assertDontSee('validation.');
    }

    /**
     * O documento do vendedor é OPCIONAL (vendedor "a confirmar"), mas
     * quando preenchido tem de ser um CNPJ ou CPF válido.
     */
    public function test_documento_do_vendedor_e_opcional_mas_validado_quando_preenchido(): void
    {
        // Em branco: nenhum erro nesse campo.
        $this->actingAs($this->admin)
            ->post(route('compras.store'), [])
            ->assertSessionDoesntHaveErrors('fornecedor_documento');

        $this->actingAs($this->admin)
            ->post(route('compras.store'), ['fornecedor_documento' => '11.111.111/1111-11'])
            ->assertSessionHasErrors([
                'fornecedor_documento' => 'Informe um CNPJ (14 dígitos) ou CPF (11 dígitos) válido — confira os números digitados.',
            ]);

        // CPF inválido também é pego.
        $this->actingAs($this->admin)
            ->post(route('compras.store'), ['fornecedor_documento' => '111.111.111-11'])
            ->assertSessionHasErrors('fornecedor_documento');
    }

    public function test_senha_atual_em_branco_pede_preenchimento_em_vez_de_dizer_incorreta(): void
    {
        $this->actingAs($this->admin)
            ->put(route('senha.trocar.update'), [])
            ->assertSessionHasErrors([
                'current_password' => 'Informe sua senha atual.',
                'password' => 'Informe a nova senha.',
            ]);

        // Senha atual preenchida mas errada => aí sim "incorreta".
        $this->actingAs($this->admin)
            ->put(route('senha.trocar.update'), ['current_password' => 'senha-errada'])
            ->assertSessionHasErrors(['current_password' => 'A senha atual informada está incorreta.']);
    }

    public function test_novo_contrato_em_branco_explica_cada_campo(): void
    {
        $this->actingAs($this->admin)
            ->post(route('contratos.store'), [])
            ->assertSessionHasErrors([
                'numero_ut' => 'Informe o número UT do contrato.',
                'cliente_id' => 'Selecione o cliente (comprador).',
                'quantidade_kg' => 'Informe a quantidade em quilos.',
                'porto' => 'Selecione o porto de embarque.',
            ]);

        $this->actingAs($this->admin)
            ->from(route('contratos.create'))
            ->followingRedirects()
            ->post(route('contratos.store'), [])
            ->assertOk()
            ->assertSee('Informe o número UT do contrato.')
            ->assertDontSee('validation.');
    }

    public function test_fixacao_em_branco_explica_cada_campo(): void
    {
        $this->actingAs($this->admin)
            ->post(route('ny.fixacoes.store'), [])
            ->assertSessionHasErrors([
                'contratos' => 'Marque ao menos um contrato para fixar.',
                'corretora' => 'Selecione a corretora.',
                'tela' => 'Escolha a tela (mês da bolsa) contra a qual está fixando.',
                'level' => 'Informe o level (preço da bolsa).',
            ]);

        $this->actingAs($this->admin)
            ->from(route('ny.index'))
            ->followingRedirects()
            ->post(route('ny.fixacoes.store'), [])
            ->assertOk()
            ->assertSee('Selecione a corretora.')
            ->assertDontSee('validation.');
    }

    public function test_classificacao_em_branco_explica_cada_campo(): void
    {
        $fornecedor = Fornecedor::create(['nome' => 'Fornecedor X', 'documento' => '12345678000199']);
        $compra = Compra::create([
            'uts' => 'UTS 1', 'data_compra' => '2026-08-01', 'fornecedor_id' => $fornecedor->id,
            'certificacao' => 'RFA', 'tipo_entrada' => 'BICA',
            'volume_contratado' => 600, 'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->put(route('compras.classificacao.update', $compra), [])
            ->assertSessionHasErrors([
                'padrao_final' => 'Selecione o padrão final da classificação.',
                'tipo_bebida' => 'Selecione o tipo de bebida.',
                'peneira_1718_sacas' => 'Preencha sacas da peneira 17/18 (use 0 se não houver).',
                'moka_pct' => 'Preencha % de moka (use 0 se não houver).',
            ]);
    }

    public function test_entrega_em_branco_explica_o_que_falta(): void
    {
        $fornecedor = Fornecedor::create(['nome' => 'Fornecedor X', 'documento' => '12345678000199']);
        $compra = Compra::create([
            'uts' => 'UTS 1', 'data_compra' => '2026-08-01', 'fornecedor_id' => $fornecedor->id,
            'certificacao' => 'RFA', 'tipo_entrada' => 'BICA',
            'volume_contratado' => 600, 'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('compras.entregas.store', $compra), [])
            ->assertSessionHasErrors([
                'mes_ano' => 'Informe o mês/ano da entrega.',
                'armazem' => 'Selecione o armazém que recebeu o café.',
                'volume_sacas' => 'Informe quantas sacas entraram no armazém.',
            ]);
    }

    public function test_financeiro_em_branco_explica_o_que_falta(): void
    {
        $fornecedor = Fornecedor::create(['nome' => 'Fornecedor X', 'documento' => '12345678000199']);
        $compra = Compra::create([
            'uts' => 'UTS 1', 'data_compra' => '2026-08-01', 'fornecedor_id' => $fornecedor->id,
            'certificacao' => 'RFA', 'tipo_entrada' => 'BICA',
            'volume_contratado' => 600, 'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->put(route('compras.financeiro.update', $compra), [])
            ->assertSessionHasErrors(['valor_saca' => 'Informe o valor da saca.']);
    }

    public function test_cadastros_do_admin_explicam_o_que_falta(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.clientes.store'), [])
            ->assertSessionHasErrors([
                'nome' => 'Informe o nome do cliente (como sai no BUYER do contrato).',
                'endereco' => 'Informe o endereço do cliente.',
            ]);

        $this->actingAs($this->admin)
            ->post(route('admin.qualidades.store'), [])
            ->assertSessionHasErrors([
                'descricao' => 'Informe a descrição da qualidade (como sai no QUALITY do contrato).',
            ]);

        $this->actingAs($this->admin)
            ->post(route('admin.corretoras.store'), [])
            ->assertSessionHasErrors([
                'nome' => 'Informe o nome da corretora ou do broker.',
                'tipo' => 'Escolha se é uma corretora nossa ou um broker de cliente.',
            ]);

        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), ['email' => 'nao-e-email'])
            ->assertSessionHasErrors([
                'name' => 'Informe o nome do usuário.',
                'email' => 'Informe um e-mail válido (ex.: nome@utrading.com.br).',
                'role_id' => 'Selecione o perfil de acesso.',
            ]);
    }

    public function test_resumo_do_erro_diz_quantos_campos_precisam_de_atencao(): void
    {
        // 1 erro => singular.
        $this->actingAs($this->admin)
            ->from(route('admin.qualidades.index'))
            ->followingRedirects()
            ->post(route('admin.qualidades.store'), [])
            ->assertOk()
            ->assertSee('1 campo precisa de atenção');

        // 2 erros => plural com a contagem.
        $this->actingAs($this->admin)
            ->from(route('admin.clientes.index'))
            ->followingRedirects()
            ->post(route('admin.clientes.store'), [])
            ->assertOk()
            ->assertSee('2 campos precisam de atenção');
    }

    public function test_paginacao_sai_em_portugues(): void
    {
        // 21 clientes => 2 páginas (paginate(20)) => botões traduzidos.
        for ($i = 1; $i <= 21; $i++) {
            Cliente::create(['nome' => "Cliente {$i}", 'endereco' => 'Endereço']);
        }

        $this->actingAs($this->admin)->get(route('admin.clientes.index'))
            ->assertOk()
            ->assertSee('Anterior')
            ->assertSee('Próxima')
            ->assertDontSee('pagination.');
    }
}
