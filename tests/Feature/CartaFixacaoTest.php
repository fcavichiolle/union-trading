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

/**
 * Carta de fixação (PRICE FIXING CONFIRMATION) — a nossa versão do documento
 * que o cliente manda depois de cada fixação.
 *
 * O que está guardado aqui é o par de números que é fácil trocar sem
 * perceber: **Average** é a média dos LEVELS (preço da bolsa) e **Final
 * Price** é a média dos preços das tranches (level + diferencial). Na carta
 * real da Alfa: média 289,50, diferencial -10, final 279,50.
 */
class CartaFixacaoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $financeiro;
    private Contrato $contrato;

    protected function setUp(): void
    {
        parent::setUp();

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

        $cliente = Cliente::create([
            'nome' => 'INICIATIVAS COMERCIALES NAVARRAS, S.A.',
            'endereco' => "Principe de Vergara, 136\nMadrid – Spain",
        ]);
        $qualidade = Qualidade::create([
            'descricao' => 'BRAZIL NATURAL ARABICA COFFEE NY 2/3 SCREEN 17/18 S.S. GOOD CUP',
        ]);

        // 38.400 kg => 640 sacas de 60 kg => 2 lotes (arábica, ÷283,49).
        $this->contrato = Contrato::create([
            'numero_ut' => '5970', 'data_contrato' => '2026-08-05',
            'cliente_id' => $cliente->id, 'cliente_nome' => $cliente->nome, 'cliente_endereco' => $cliente->endereco,
            'qualidade_id' => $qualidade->id, 'qualidade_descricao' => $qualidade->descricao,
            'buyer_ref' => '31748', 'tipo_cafe' => 'ARABICA', 'certificado' => 'RFA',
            'quantidade_kg' => 38400, 'tipo_container' => '20', 'embalagem' => 'Bulk Liner',
            'incoterms' => 'FOB', 'porto' => 'SANTOS', 'diferencial' => '-10.00',
            'mes_fixacao' => 'H7', 'created_by' => $this->admin->id,
        ]);
    }

    private function fixar(array $overrides = []): Fixacao
    {
        return Fixacao::create(array_merge([
            'contrato_id' => $this->contrato->id, 'corretora' => 'StoneX East Coast',
            'tela' => 'H7', 'lotes' => 2, 'level' => '289.50', 'diferencial' => '-10.00',
            'created_by' => $this->admin->id,
        ], $overrides));
    }

    /**
     * Ajusta o contrato para ter N lotes.
     *
     * `lotes` é CALCULADO no `saving` do model a partir de `quantidade_kg`
     * (sacas ÷ 283,49), então `update(['lotes' => 4])` é ignorado em silêncio.
     * Daí mexer no peso e conferir o resultado, em vez de confiar.
     */
    private function comLotes(int $lotes): void
    {
        $this->contrato->update(['quantidade_kg' => (int) round($lotes * Contrato::DIVISOR_ARABICA * 60)]);
        $this->contrato = $this->contrato->fresh();

        $this->assertSame($lotes, (int) $this->contrato->lotes, 'o cenário do teste não montou os lotes esperados');
    }

    private function html(): string
    {
        $contrato = $this->contrato->fresh(['fixacoes']);
        $saldo = (int) $contrato->lotes;

        $linhas = $contrato->fixacoes->map(function ($f) use (&$saldo) {
            $saldo = max(0, $saldo - (int) $f->lotes);

            return ['fixacao' => $f, 'saldo' => $saldo];
        });

        return view('contratos.carta-fixacao', [
            'contrato' => $contrato,
            'linhas' => $linhas,
            'resumo' => $contrato->resumoDeFixacao(),
        ])->render();
    }

    /** Reproduz a carta real: média 289,50, diferencial -10, final 279,50. */
    public function test_media_e_do_level_e_preco_final_traz_o_diferencial(): void
    {
        $this->fixar();

        $resumo = $this->contrato->fresh(['fixacoes'])->resumoDeFixacao();

        $this->assertEqualsWithDelta(289.50, $resumo['media_level'], 0.001);
        $this->assertEqualsWithDelta(-10.00, $resumo['media_diferencial'], 0.001);
        $this->assertEqualsWithDelta(279.50, $resumo['preco_final'], 0.001);
        $this->assertTrue($resumo['completo']);

        $html = $this->html();
        $this->assertStringContainsString('289,50', $html);
        $this->assertStringContainsString('279,50', $html);
    }

    /** Média PONDERADA: uma tranche de 1 lote não vale o mesmo que uma de 3. */
    public function test_medias_sao_ponderadas_pelos_lotes(): void
    {
        $this->comLotes(4);

        $this->fixar(['lotes' => 3, 'level' => '300.00']);
        $this->fixar(['lotes' => 1, 'level' => '340.00']);

        // (300*3 + 340*1) / 4 = 310 — a média simples daria 320.
        $this->assertEqualsWithDelta(310.00, $this->contrato->fresh(['fixacoes'])->resumoDeFixacao()['media_level'], 0.001);
    }

    /** Coluna Balance: quantos lotes sobram depois de cada tranche. */
    public function test_saldo_corre_linha_a_linha(): void
    {
        $this->comLotes(4);
        $this->fixar(['lotes' => 3]);
        $this->fixar(['lotes' => 1]);

        $html = $this->html();

        // A coluna Balance tem classe própria: sem isso a asserção casaria
        // com a coluna Lots, que tem os mesmos números.
        preg_match_all('/<td class="num saldo">(\d+)<\/td>/', $html, $m);
        $this->assertSame(['1', '0'], $m[1]);
        $this->assertStringNotContainsString('Partial fixation', $html);
    }

    /** Carta com saldo em aberto precisa dizer que é parcial. */
    public function test_carta_parcial_avisa_que_ainda_falta_fixar(): void
    {
        $this->comLotes(4);
        $this->fixar(['lotes' => 1]);

        $html = $this->html();

        $this->assertStringContainsString('Partial fixation', $html);
        $this->assertStringContainsString('1 of 4 lot(s) fixed', $html);
        $this->assertStringContainsString('3 lot(s) still open', $html);
    }

    /** A posição sai por extenso, em inglês, como na carta da contraparte. */
    public function test_posicao_da_bolsa_por_extenso(): void
    {
        $this->assertSame('ICE NEW YORK / March,2027', Contrato::bolsaEMesPorExtenso('H7'));
        $this->assertSame('ICE NEW YORK / December,2026', Contrato::bolsaEMesPorExtenso('Z6'));
        $this->assertSame('ICE LONDON / September,2026', Contrato::bolsaEMesPorExtenso('Sep_2026'));
        $this->assertNull(Contrato::bolsaEMesPorExtenso(null));
        // Código irreconhecível volta cru: melhor isso do que posição inventada.
        $this->assertSame('XX', Contrato::bolsaEMesPorExtenso('XX'));
    }

    /** Somos o SELLER: o UT é a nossa ref., a do comprador é a buyer_ref. */
    public function test_referencias_saem_do_lado_certo(): void
    {
        $this->fixar();
        $html = $this->html();

        $this->assertMatchesRegularExpression('/Seller\'s Ref\.<\/td><td class="b">UT 5970/', $html);
        $this->assertMatchesRegularExpression('/Buyer\'s Ref\.<\/td><td>31748/', $html);
    }

    public function test_baixa_o_pdf_com_nome_proprio(): void
    {
        $this->fixar();

        $resposta = $this->actingAs($this->admin)
            ->get(route('contratos.carta-fixacao', $this->contrato))
            ->assertOk();

        $this->assertSame('application/pdf', $resposta->headers->get('content-type'));
        $this->assertStringContainsString('Price_fixing_UT_5970_', $resposta->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $resposta->getContent());
    }

    /** Sem tranche não há o que confirmar — e o botão nem aparece na tela. */
    public function test_contrato_sem_fixacao_nao_gera_carta(): void
    {
        $this->actingAs($this->admin)
            ->get(route('contratos.carta-fixacao', $this->contrato))
            ->assertRedirect(route('contratos.show', $this->contrato))
            ->assertSessionHas('status');

        $this->actingAs($this->admin)
            ->get(route('contratos.show', $this->contrato))
            ->assertOk()
            ->assertDontSee('Carta de fixação (PDF)');
    }

    public function test_botao_aparece_quando_ha_fixacao(): void
    {
        $this->fixar();

        $this->actingAs($this->admin)
            ->get(route('contratos.show', $this->contrato))
            ->assertOk()
            ->assertSee('Carta de fixação (PDF)');
    }

    public function test_perfil_sem_permissao_nao_gera_a_carta(): void
    {
        $this->fixar();

        $this->actingAs($this->financeiro)
            ->get(route('contratos.carta-fixacao', $this->contrato))
            ->assertForbidden();
    }
}
