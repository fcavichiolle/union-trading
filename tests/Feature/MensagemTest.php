<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Mensagem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Canal geral de mensagens (mural interno).
 *
 * Decisões que os testes guardam:
 *  - todo perfil lê e escreve (os perfis limitam o que se ALTERA nos
 *    registros, não a conversa da equipe);
 *  - não lidas = mensagens de OUTROS depois da última visita ao canal;
 *  - apagar é do autor ou do admin, e admin apagando a de outro fica no
 *    AuditLog (único lugar onde o texto sobrevive).
 */
class MensagemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $compras;
    private User $diretoria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->usuario('admin', 'Administrador', 'admin@teste.com');
        $this->compras = $this->usuario('compras', 'Compras', 'compras@teste.com');
        $this->diretoria = $this->usuario('diretoria', 'Diretoria', 'dir@teste.com');
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

    /* ---------------- escrever e ler ---------------- */

    public function test_envia_mensagem_e_ela_aparece_para_a_equipe(): void
    {
        $this->actingAs($this->compras)
            ->post(route('mensagens.store'), ['texto' => 'Mercado abriu em alta hoje'])
            ->assertRedirect(route('mensagens.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('mensagens', [
            'user_id' => $this->compras->id,
            'texto' => 'Mercado abriu em alta hoje',
        ]);

        // Outro usuário vê a mensagem e quem escreveu.
        $this->actingAs($this->admin)->get(route('mensagens.index'))->assertOk()
            ->assertSee('Mercado abriu em alta hoje')
            ->assertSee('Compras');
    }

    public function test_mensagem_vazia_e_longa_demais_sao_recusadas(): void
    {
        $this->actingAs($this->compras)->post(route('mensagens.store'), ['texto' => ''])
            ->assertSessionHasErrors(['texto' => 'Escreva a mensagem antes de enviar.']);

        $this->actingAs($this->compras)->post(route('mensagens.store'), ['texto' => str_repeat('a', 2001)])
            ->assertSessionHasErrors('texto');

        $this->assertDatabaseCount('mensagens', 0);
    }

    /** Todo perfil participa — inclusive diretoria, que é leitura no resto. */
    public function test_todos_os_perfis_escrevem_no_canal(): void
    {
        foreach ([$this->admin, $this->compras, $this->diretoria] as $usuario) {
            $this->actingAs($usuario)->get(route('mensagens.index'))->assertOk();
            $this->actingAs($usuario)
                ->post(route('mensagens.store'), ['texto' => 'oi de ' . $usuario->name])
                ->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('mensagens', 3);
    }

    public function test_visitante_sem_login_nao_entra(): void
    {
        $this->get(route('mensagens.index'))->assertRedirect(route('login'));
        $this->post(route('mensagens.store'), ['texto' => 'x'])->assertRedirect(route('login'));
    }

    /** O texto é escapado: mensagem não pode virar HTML na tela de ninguém. */
    public function test_texto_do_usuario_nao_vira_html(): void
    {
        $this->mensagem($this->compras, '<script>alert(1)</script>');

        $resposta = $this->actingAs($this->admin)->get(route('mensagens.index'))->assertOk();

        $resposta->assertDontSee('<script>alert(1)</script>', false);
        $resposta->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    /* ---------------- não lidas ---------------- */

    public function test_badge_conta_so_mensagem_de_outra_pessoa(): void
    {
        $this->mensagem($this->compras, 'de outro');
        $this->mensagem($this->admin, 'minha');

        $badges = app(\App\Services\PainelInicial::class)->badgesMenu($this->admin);

        $this->assertSame(1, $badges['mensagens.index'] ?? 0);
    }

    public function test_abrir_o_canal_zera_as_nao_lidas(): void
    {
        $this->mensagem($this->compras);
        $this->assertSame(1, Mensagem::naoLidasPor($this->admin)->count());

        $this->actingAs($this->admin)->get(route('mensagens.index'))->assertOk();

        $this->assertSame(0, Mensagem::naoLidasPor($this->admin->fresh())->count());
    }

    public function test_mensagem_anterior_a_visita_nao_conta_como_nao_lida(): void
    {
        Carbon::setTestNow('2026-08-12 09:00:00');
        $this->mensagem($this->compras, 'antes da visita');

        Carbon::setTestNow('2026-08-12 10:00:00');
        $this->actingAs($this->admin)->get(route('mensagens.index'))->assertOk();

        Carbon::setTestNow('2026-08-12 11:00:00');
        $this->mensagem($this->compras, 'depois da visita');

        $this->assertSame(1, Mensagem::naoLidasPor($this->admin->fresh())->count());

        Carbon::setTestNow();
    }

    /* ---------------- polling ---------------- */

    public function test_polling_devolve_so_as_mensagens_novas(): void
    {
        $primeira = $this->mensagem($this->compras, 'primeira');
        $segunda = $this->mensagem($this->compras, 'segunda');

        $resposta = $this->actingAs($this->admin)
            ->getJson(route('mensagens.novas', ['depois' => $primeira->id]))
            ->assertOk();

        $mensagens = $resposta->json('mensagens');

        $this->assertCount(1, $mensagens);
        $this->assertSame($segunda->id, $mensagens[0]['id']);
        $this->assertSame('segunda', $mensagens[0]['texto']);
        $this->assertFalse($mensagens[0]['minha']);
        // Admin pode apagar a de qualquer um.
        $this->assertTrue($mensagens[0]['pode_apagar']);
    }

    public function test_polling_marca_como_lido(): void
    {
        $this->mensagem($this->compras);

        $this->actingAs($this->admin)->getJson(route('mensagens.novas', ['depois' => 0]))->assertOk();

        $this->assertSame(0, Mensagem::naoLidasPor($this->admin->fresh())->count());
    }

    public function test_carrega_mensagens_anteriores(): void
    {
        $ids = collect(range(1, 60))->map(fn ($i) => $this->mensagem($this->compras, "msg {$i}")->id);

        // A tela abre com as últimas POR_PAGINA; o botão busca as de antes.
        $primeiraNaTela = $ids[60 - Mensagem::POR_PAGINA];

        $resposta = $this->actingAs($this->admin)
            ->getJson(route('mensagens.novas', ['antes' => $primeiraNaTela]))
            ->assertOk();

        $this->assertCount(10, $resposta->json('mensagens'));
        $this->assertFalse($resposta->json('tem_anteriores'));
    }

    public function test_envio_por_json_devolve_a_mensagem_montada(): void
    {
        $resposta = $this->actingAs($this->compras)
            ->postJson(route('mensagens.store'), ['texto' => 'via fetch'])
            ->assertOk();

        $this->assertSame('via fetch', $resposta->json('mensagem.texto'));
        $this->assertTrue($resposta->json('mensagem.minha'));
        $this->assertSame('C', substr($resposta->json('mensagem.iniciais'), 0, 1));
    }

    /* ---------------- apagar ---------------- */

    public function test_autor_apaga_a_propria_mensagem(): void
    {
        $mensagem = $this->mensagem($this->compras);

        $this->actingAs($this->compras)->delete(route('mensagens.destroy', $mensagem))
            ->assertRedirect(route('mensagens.index'));

        $this->assertDatabaseCount('mensagens', 0);
    }

    public function test_nao_apaga_mensagem_de_outra_pessoa(): void
    {
        $mensagem = $this->mensagem($this->compras);

        $this->actingAs($this->diretoria)->delete(route('mensagens.destroy', $mensagem))
            ->assertForbidden();

        $this->assertDatabaseCount('mensagens', 1);
    }

    public function test_admin_apaga_de_qualquer_um_e_isso_fica_no_audit_log(): void
    {
        $mensagem = $this->mensagem($this->compras, 'mensagem fora de hora');

        $this->actingAs($this->admin)->delete(route('mensagens.destroy', $mensagem))
            ->assertRedirect(route('mensagens.index'));

        $this->assertDatabaseCount('mensagens', 0);

        $log = AuditLog::where('acao', 'mensagem.excluida')->firstOrFail();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertStringContainsString('mensagem fora de hora', $log->descricao);
        $this->assertStringContainsString('Compras', $log->descricao);
    }

    /** Apagar a própria não gera log — não é ação sobre outra pessoa. */
    public function test_autor_apagando_a_propria_nao_gera_log(): void
    {
        $mensagem = $this->mensagem($this->admin);

        $this->actingAs($this->admin)->delete(route('mensagens.destroy', $mensagem));

        $this->assertSame(0, AuditLog::where('acao', 'mensagem.excluida')->count());
    }
}
