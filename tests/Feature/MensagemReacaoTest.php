<?php

namespace Tests\Feature;

use App\Models\Mensagem;
use App\Models\MensagemReacao;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Reações, seletor de emojis e chat flutuante do canal da equipe.
 *
 * Decisões que os testes guardam:
 *  - a reação é de uma lista FECHADA (validada no servidor): não é campo
 *    livre onde caiba qualquer texto;
 *  - clicar de novo no mesmo emoji tira a reação;
 *  - reação em mensagem antiga chega pelo polling (não é "mensagem nova");
 *  - o chat flutuante existe em toda tela, menos na de Mensagens, e só
 *    olhar o contador NÃO marca o canal como lido.
 */
class MensagemReacaoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $compras;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->usuario('admin', 'Administrador', 'admin@teste.com');
        $this->compras = $this->usuario('compras', 'Compras', 'compras@teste.com');
    }

    private function usuario(string $slug, string $nome, string $email): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['nome' => $nome]);

        return User::create([
            'role_id' => $role->id, 'name' => $nome, 'email' => $email,
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);
    }

    private function mensagem(User $autor, string $texto = 'Bom dia'): Mensagem
    {
        return Mensagem::create(['user_id' => $autor->id, 'texto' => $texto]);
    }

    private function reagir(User $quem, Mensagem $m, string $emoji)
    {
        return $this->actingAs($quem)->postJson(route('mensagens.reagir', $m), ['emoji' => $emoji]);
    }

    public function test_reagir_liga_e_clicar_de_novo_desliga(): void
    {
        $m = $this->mensagem($this->admin);

        $this->reagir($this->compras, $m, '👍')
            ->assertOk()
            ->assertJsonPath('reacoes.0.emoji', '👍')
            ->assertJsonPath('reacoes.0.total', 1)
            ->assertJsonPath('reacoes.0.minha', true)
            ->assertJsonPath('reacoes.0.quem', 'Você');
        $this->assertSame(1, MensagemReacao::count());

        $this->reagir($this->compras, $m, '👍')->assertOk()->assertJsonPath('reacoes', []);
        $this->assertSame(0, MensagemReacao::count());
    }

    public function test_reacoes_de_pessoas_diferentes_somam_e_dizem_quem_reagiu(): void
    {
        $m = $this->mensagem($this->admin);

        $this->reagir($this->compras, $m, '✅');
        $this->reagir($this->admin, $m, '✅');
        $resposta = $this->reagir($this->admin, $m, '👀')->assertOk();

        // Na ordem da lista de reações: 👀 vem depois de ✅.
        $resposta->assertJsonPath('reacoes.0.emoji', '✅')
            ->assertJsonPath('reacoes.0.total', 2)
            ->assertJsonPath('reacoes.0.minha', true)
            ->assertJsonPath('reacoes.1.emoji', '👀')
            ->assertJsonPath('reacoes.1.total', 1);
        $this->assertStringContainsString('Compras', $resposta->json('reacoes.0.quem'));
    }

    /** Lista fechada: nada de texto livre (nem HTML) no lugar do emoji. */
    public function test_so_aceita_emoji_da_lista(): void
    {
        $m = $this->mensagem($this->admin);

        $this->reagir($this->compras, $m, '<script>alert(1)</script>')->assertStatus(422);
        $this->reagir($this->compras, $m, '🦄')->assertStatus(422);
        $this->actingAs($this->compras)->postJson(route('mensagens.reagir', $m), [])->assertStatus(422);

        $this->assertSame(0, MensagemReacao::count());
    }

    public function test_reagir_exige_login(): void
    {
        $m = $this->mensagem($this->admin);

        $this->postJson(route('mensagens.reagir', $m), ['emoji' => '👍'])->assertUnauthorized();
    }

    public function test_tela_mostra_as_reacoes_e_o_seletor_de_emojis(): void
    {
        $m = $this->mensagem($this->admin);
        $m->alternarReacao($this->compras, '🙏');

        $this->actingAs($this->admin)->get(route('mensagens.index'))
            ->assertOk()
            ->assertSee('data-emoji="🙏"', false)
            ->assertSee('title="Compras"', false)
            ->assertSee('data-chat-emoji-btn', false)
            ->assertSee('data-reagir', false);
    }

    /** Reação em mensagem antiga não é mensagem nova: vem no `reacoes` do polling. */
    public function test_polling_devolve_as_reacoes_do_trecho_aberto(): void
    {
        $antiga = $this->mensagem($this->admin, 'antiga');
        $semReacao = $this->mensagem($this->admin, 'sem reação');
        $antiga->alternarReacao($this->compras, '👍');

        $dados = $this->actingAs($this->admin)
            ->getJson(route('mensagens.novas', ['depois' => $semReacao->id, 'visiveis_de' => $antiga->id]))
            ->assertOk()
            ->json();

        $this->assertSame([], $dados['mensagens']);
        $this->assertSame('👍', $dados['reacoes'][$antiga->id][0]['emoji']);
        $this->assertArrayNotHasKey($semReacao->id, $dados['reacoes']);
    }

    public function test_apagar_a_mensagem_leva_as_reacoes_junto(): void
    {
        $m = $this->mensagem($this->admin);
        $m->alternarReacao($this->compras, '👍');

        $this->actingAs($this->admin)->deleteJson(route('mensagens.destroy', $m))
            ->assertOk()
            ->assertJsonPath('removida', $m->id);

        $this->assertSame(0, Mensagem::count());
        $this->assertSame(0, MensagemReacao::count());
    }

    /* ---------------- chat flutuante ---------------- */

    public function test_chat_flutuante_aparece_nas_outras_telas_e_nao_na_de_mensagens(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="chatFlutuante"', false);

        $this->actingAs($this->admin)->get(route('mensagens.index'))
            ->assertOk()
            ->assertDontSee('id="chatFlutuante"', false);
    }

    /** O ícone mostra quantas há por ler — e olhar o número não marca como lido. */
    public function test_resumo_conta_as_nao_lidas_sem_marcar_como_lido(): void
    {
        $this->mensagem($this->compras, 'uma');
        $this->mensagem($this->compras, 'duas, @Administrador');

        $this->actingAs($this->admin)->getJson(route('mensagens.resumo'))
            ->assertOk()
            ->assertJson(['nao_lidas' => 2, 'citado' => true]);

        // Continua por ler: o badge do menu não zerou.
        $this->actingAs($this->admin)->getJson(route('mensagens.resumo'))->assertJson(['nao_lidas' => 2]);
        $this->actingAs($this->admin)->get(route('dashboard'))->assertSee('chatf__badge', false);
    }

    /** Abrir o chat flutuante carrega as últimas mensagens e marca como lido. */
    public function test_abrir_o_chat_flutuante_carrega_as_recentes_e_marca_como_lido(): void
    {
        $this->mensagem($this->compras, 'Chegou o container');

        $dados = $this->actingAs($this->admin)->getJson(route('mensagens.novas', ['recentes' => 1]))->assertOk()->json();

        $this->assertCount(1, $dados['mensagens']);
        $this->assertSame(1, $dados['nao_lidas']);
        $this->assertFalse($dados['tem_anteriores']);
        $this->assertContains('Compras', array_column($dados['usuarios'], 'nome'));

        $this->actingAs($this->admin)->getJson(route('mensagens.resumo'))->assertJson(['nao_lidas' => 0]);
    }
}
