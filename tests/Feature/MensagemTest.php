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

        // A coluna e cifrada: a conferencia e pelo model (que decifra).
        $mensagem = Mensagem::firstOrFail();
        $this->assertSame($this->compras->id, $mensagem->user_id);
        $this->assertSame('Mercado abriu em alta hoje', $mensagem->texto);

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
        $this->assertSame('segunda', $this->textoDe($mensagens[0]));
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

        $this->assertSame('via fetch', $this->textoDe($resposta->json('mensagem')));
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
        // Registra QUEM e QUANDO, nunca o conteudo: com o texto cifrado no
        // banco, uma copia em claro no log seria porta dos fundos.
        $this->assertStringContainsString('Compras', $log->descricao);
        $this->assertStringNotContainsString('mensagem fora de hora', $log->descricao);
    }

    /** Apagar a própria não gera log — não é ação sobre outra pessoa. */
    public function test_autor_apagando_a_propria_nao_gera_log(): void
    {
        $mensagem = $this->mensagem($this->admin);

        $this->actingAs($this->admin)->delete(route('mensagens.destroy', $mensagem));

        $this->assertSame(0, AuditLog::where('acao', 'mensagem.excluida')->count());
    }

    /* ---------------- criptografia no banco ---------------- */

    /**
     * O que a criptografia entrega: quem lê a tabela (dump, backup, acesso
     * só ao MySQL) não vê o texto.
     */
    public function test_texto_fica_cifrado_na_coluna(): void
    {
        $this->mensagem($this->compras, 'preço combinado com a fazenda: 1.180');

        $bruto = \Illuminate\Support\Facades\DB::table('mensagens')->value('texto');

        $this->assertNotSame('preço combinado com a fazenda: 1.180', $bruto);
        $this->assertStringNotContainsString('1.180', $bruto);
        $this->assertStringNotContainsString('fazenda', $bruto);
        // O payload do Laravel é um JSON base64 com iv/value/mac.
        $this->assertArrayHasKey('mac', json_decode(base64_decode($bruto), true) ?? []);

        // E o sistema continua lendo normalmente.
        $this->assertSame('preço combinado com a fazenda: 1.180', Mensagem::first()->texto);
    }

    /** Mensagem longa não estoura a coluna depois de cifrada. */
    public function test_mensagem_no_limite_cabe_cifrada(): void
    {
        $longa = str_repeat('a', 2000);

        $this->actingAs($this->compras)
            ->post(route('mensagens.store'), ['texto' => $longa])
            ->assertSessionHasNoErrors();

        $this->assertSame($longa, Mensagem::first()->texto);
    }

    /* ---------------- menção com @nome ---------------- */

    public function test_mencao_pelo_nome_inteiro_e_pelo_primeiro_nome(): void
    {
        $marina = $this->usuario('compras', 'Marina Alves', 'marina@teste.com');

        $m1 = $this->mensagem($this->compras, 'Bom dia @Marina Alves, confere a UTS 7311?');
        $m2 = $this->mensagem($this->compras, 'obrigado @marina');

        $this->assertSame([$marina->id], $m1->fresh()->mencionados->pluck('id')->all());
        $this->assertSame([$marina->id], $m2->fresh()->mencionados->pluck('id')->all());
    }

    /** "@Ana" não pode capturar "@Ana Paula" (casa o nome mais longo). */
    public function test_mencao_casa_o_nome_mais_longo(): void
    {
        $ana = $this->usuario('compras', 'Ana', 'ana@teste.com');
        $anaPaula = $this->usuario('compras', 'Ana Paula', 'anapaula@teste.com');

        $mensagem = $this->mensagem($this->compras, 'oi @Ana Paula');
        $ids = $mensagem->fresh()->mencionados->pluck('id')->all();

        $this->assertContains($anaPaula->id, $ids);
        $this->assertNotContains($ana->id, $ids);
    }

    public function test_citar_a_si_mesmo_nao_gera_aviso(): void
    {
        $mensagem = $this->mensagem($this->compras, 'eu, @Compras, assumo isso');

        $this->assertCount(0, $mensagem->fresh()->mencionados);
    }

    public function test_badge_marca_quando_alguem_me_cita(): void
    {
        // Mensagem comum: badge normal.
        $this->mensagem($this->compras, 'bom dia a todos');
        $badges = app(\App\Services\PainelInicial::class)->badgesMenu($this->admin->fresh());
        $this->assertSame(1, $badges['mensagens.index']);
        $this->assertArrayNotHasKey('mensagens.mencao', $badges);

        // Citando o admin: badge de citação.
        $this->mensagem($this->compras, '@Administrador consegue olhar?');
        $badges = app(\App\Services\PainelInicial::class)->badgesMenu($this->admin->fresh());
        $this->assertSame(2, $badges['mensagens.index']);
        $this->assertTrue($badges['mensagens.mencao']);
    }

    public function test_citacao_lida_para_de_marcar(): void
    {
        $this->mensagem($this->compras, '@Administrador olha isso');

        $this->actingAs($this->admin)->get(route('mensagens.index'))->assertOk();

        $badges = app(\App\Services\PainelInicial::class)->badgesMenu($this->admin->fresh());
        $this->assertArrayNotHasKey('mensagens.mencao', $badges);
    }

    /** A tela destaca a menção — e marca a mensagem que cita quem está lendo. */
    public function test_tela_destaca_a_mencao_e_avisa_quem_foi_citado(): void
    {
        $this->mensagem($this->compras, 'Fala @Administrador, fechou?');

        $resposta = $this->actingAs($this->admin)->get(route('mensagens.index'))->assertOk();

        $resposta->assertSee('class="mencao mencao--eu"', false)
            ->assertSee('citou você')
            ->assertSee('msg--citou', false);
    }

    /** Menção a quem não existe fica texto comum, sem quebrar nada. */
    public function test_arroba_de_quem_nao_existe_e_texto_comum(): void
    {
        $mensagem = $this->mensagem($this->compras, 'falei com @Fulano ontem');

        $this->assertCount(0, $mensagem->fresh()->mencionados);

        $this->actingAs($this->admin)->get(route('mensagens.index'))->assertOk()
            ->assertSee('falei com @Fulano ontem');
    }

    /** O autocomplete recebe a lista de quem pode ser citado. */
    public function test_tela_manda_os_usuarios_para_o_autocomplete(): void
    {
        $resposta = $this->actingAs($this->admin)->get(route('mensagens.index'))->assertOk();

        $usuarios = $resposta->viewData('usuarios');

        $this->assertCount(3, $usuarios);
        $this->assertSame(['Administrador', 'Compras', 'Diretoria'], $usuarios->pluck('nome')->all());
    }

    /** Usuário suspenso sai do autocomplete: não entra mais no sistema. */
    public function test_usuario_inativo_nao_aparece_para_citar(): void
    {
        $this->diretoria->forceFill(['active' => false])->save();

        $usuarios = $this->actingAs($this->admin)->get(route('mensagens.index'))
            ->assertOk()->viewData('usuarios');

        $this->assertSame(['Administrador', 'Compras'], $usuarios->pluck('nome')->all());
    }

    /** Junta os pedacos do texto que a tela recebe. */
    private function textoDe(array $mensagem): string
    {
        return collect($mensagem['segmentos'])->pluck('texto')->implode('');
    }
}
