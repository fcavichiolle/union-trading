<?php

namespace Tests\Feature;

use App\Models\Classificacao;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Contrato;
use App\Models\Corretora;
use App\Models\Fixacao;
use App\Models\Fornecedor;
use App\Models\Mensagem;
use App\Models\Qualidade;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * GERADOR das páginas estáticas da demo (docs/, publicadas no GitHub Pages).
 *
 * A demo vivia sendo escrita à mão e por isso atrasava em relação ao sistema
 * (chegou a mostrar o modelo antigo, sem entregas nem liquidação). Aqui as
 * páginas saem das **views de verdade**: monta-se um cenário fictício, as
 * rotas são renderizadas com um admin logado e os links absolutos viram
 * nomes de arquivo. Quando a tela muda no app, basta rodar o gerador.
 *
 *     GERAR_DEMO=1 php artisan test --filter=GerarDemo
 *
 * Fora isso o teste é PULADO — ele escreve em docs/, e `php artisan test`
 * não deveria mexer em arquivo publicado.
 *
 * DADOS SÃO FICTÍCIOS DE PROPÓSITO. A demo é pública: nome de fornecedor,
 * CNPJ e cliente reais não entram aqui. Os dois CNPJs usados são os de
 * exemplo clássicos (11.222.333/0001-81 e 12.345.678/0001-95) e não há
 * nenhum CPF — CPF é dado pessoal e não tem por que estar numa página
 * aberta na internet.
 *
 * Páginas que o gerador NÃO cobre (tela-ny.html, cotacoes.html e os
 * cadastros) continuam mantidas à mão: elas têm JS de demonstração escrito
 * na unha, que uma renderização crua apagaria.
 */
class GerarDemoTest extends TestCase
{
    use RefreshDatabase;

    /** Usuário exibido no header — o mesmo das páginas mantidas à mão. */
    private const USUARIO_DEMO = 'QA Teste Union';

    private User $admin;

    /** @var array<string, Compra> */
    private array $compras = [];

    public function test_gera_as_paginas_estaticas_da_demo(): void
    {
        if (! getenv('GERAR_DEMO') && ! env('GERAR_DEMO')) {
            $this->markTestSkipped('Gerador da demo — rode com GERAR_DEMO=1 php artisan test --filter=GerarDemo');
        }

        // Data fixa: sem isso cada rodada gera um diff só de horário.
        Carbon::setTestNow('2026-08-11 09:30:00');

        $this->semear();

        $paginas = [
            'inicio.html' => route('dashboard'),
            'compras.html' => route('compras.index'),
            'compra.html' => route('compras.show', $this->compras['7311']),
            'compra-liquidada.html' => route('compras.show', $this->compras['7322']),
            'compra-nova.html' => route('compras.create'),
            // Classificação: o visitante da demo precisa dela para fechar o
            // ciclo (lançar compra -> entregar -> classificar).
            'compra-classificacao.html' => route('compras.classificacao.edit', $this->compras['7311']),
            // O Estoque sai do mesmo cenário: sem isso a demo contava duas
            // histórias (painel com um total, estoque com outro).
            'relatorio.html' => route('relatorio.index'),
            // Canal da equipe. Na demo ele é ilustração: o envio precisa de
            // backend, então as mensagens de exemplo ficam só para mostrar
            // como a tela funciona.
            'mensagens.html' => route('mensagens.index'),
        ];

        foreach ($paginas as $arquivo => $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

            file_put_contents(base_path('docs/' . $arquivo), $this->estatico($html));
        }

        // Guarda contra os dois erros clássicos da demo: URL de rota que
        // sobrou e link com sufixo colado ("compra.html/editar").
        foreach (array_keys($paginas) as $arquivo) {
            $conteudo = file_get_contents(base_path('docs/' . $arquivo));
            $this->assertStringNotContainsString($this->baseDaApp(), $conteudo, "Sobrou URL de rota em {$arquivo}");
            $this->assertDoesNotMatchRegularExpression('#(href|action)="[^"]*\.html/#', $conteudo, "Link com sufixo colado em {$arquivo}");
            $this->assertStringNotContainsString('<!DOCTYPE html>', substr($conteudo, 20), "Documento duplicado em {$arquivo}");
        }

        Carbon::setTestNow();
    }

    /* ==================================================================
     * Cenário fictício
     * ================================================================== */

    private function semear(): void
    {
        $role = Role::create(['slug' => 'admin', 'nome' => 'Administrador']);

        $this->admin = User::create([
            'role_id' => $role->id,
            'name' => self::USUARIO_DEMO,
            'email' => 'demo@example.com',
            'password' => Hash::make('demo-nao-usada'),
            'force_password_change' => false,
            'active' => true,
        ]);

        // Vendedores fictícios e SEM DOCUMENTO NENHUM: a demo é pública, e
        // CNPJ/CPF (mesmo de exemplo) não têm por que aparecer numa página
        // aberta na internet. Todos ficam como "a confirmar", que é um
        // estado real do sistema — a mesa fecha negócio antes de ter o
        // documento.
        $santaClara = Fornecedor::create(['nome' => 'FAZENDA SANTA CLARA LTDA']);
        $rioVerde = Fornecedor::create(['nome' => 'COOPERATIVA VALE DO RIO VERDE']);
        $boaVista = Fornecedor::create(['nome' => 'SÍTIO BOA VISTA AGROPECUÁRIA']);
        $tresBarras = Fornecedor::create(['nome' => 'FAZENDA TRÊS BARRAS']);

        // ---- Compras completas (nada pendente) ----
        $c = $this->compra('7301', $santaClara, '2026-07-28', 'RFA', 320, 1150, 'POSTO', padrao: 'GOOD_CUP');
        $this->entrega($c, '2026-07-30', 'SAAG', 320, 'L-2026-0401');
        $this->classificar($c, 320);

        $c = $this->compra('7302', $santaClara, '2026-07-30', '4C', 554, 1240, 'POSTO');
        $this->entrega($c, '2026-07-31', 'SAAG', 300, 'L-2026-0403');
        $this->entrega($c, '2026-08-06', 'QUALITE', 254, 'L-2026-0404');
        $this->classificar($c, 554);

        // Padrão novo (agosto/2026), que só é possível porque a coluna deixou
        // de ser ENUM.
        $c = $this->compra('7305', $rioVerde, '2026-08-01', 'EUDR', 480, 1090, 'RETIRAR',
            padrao: 'BICA_VERY_GOOD_CUP', bebida: 'DURO_2RY');
        $this->entrega($c, '2026-08-04', 'QUALITE', 480, 'L-2026-0407');
        $this->classificar($c, 480);

        // ---- A estrela da demo: entrou MAIS que o contratado, em dois
        //      armazéns, e uma das entregas ainda está sem nº de lote. ----
        $c = $this->compras['7311'] = $this->compra(
            '7311', $boaVista, '2026-08-03', '4C_EUDR', 600, 1180, 'RETIRAR',
            padrao: 'VERY_GOOD_CUP', bebida: 'DURO_1RY',
            pagamentoObs: '90% na entrega, saldo na classificação'
        );
        // Dias diferentes de propósito: é o que a auditoria procura.
        $this->entrega($c, '2026-08-07', 'QUALITE', 350, 'L-2026-0412', peso: 21015);
        $this->entrega($c, '2026-08-21', 'DINAMO_MACHADO', 270, null);
        $this->classificar($c, 620);

        // ---- Faltando entregar, sem classificação e sem preço ----
        $c = $this->compra('7314', $tresBarras, '2026-08-04', 'SEM_CERT', 500);
        $this->entrega($c, '2026-08-12', 'SAAG', 320, 'L-2026-0415');

        // ---- Diferença pequena para cima, esperando decisão ----
        $c = $this->compra('7318', $santaClara, '2026-08-05', 'RFA', 250, 1160, 'POSTO', padrao: 'GOOD_CUP');
        $this->entrega($c, '2026-08-13', 'SAAG', 260, 'L-2026-0418');
        $this->classificar($c, 260);

        // ---- Liquidada: entraram 590 das 600 e alguém decidiu encerrar ----
        $c = $this->compras['7322'] = $this->compra(
            '7322', $rioVerde, '2026-08-06', '4C', 600, 1095, 'POSTO',
            padrao: 'GOOD_CUP', bebida: 'DURO_2RY',
            pagamentoObs: 'Pagamento contra romaneio do armazém'
        );
        $this->entrega($c, '2026-07-24', 'SAAG', 300, 'L-2026-0421');
        $this->entrega($c, '2026-08-18', 'SAAG', 290, 'L-2026-0422');
        $this->classificar($c, 590);
        $c->update(['liquidada_em' => '2026-08-10 16:20:00', 'liquidada_por' => $this->admin->id]);

        // ---- Conilon: sem padrão nem tipo de bebida ----
        $c = $this->compra('7324', $tresBarras, '2026-08-06', 'SEM_CERT', 450, 780, 'POSTO',
            tipo: 'CONILON');
        $this->entrega($c, '2026-08-14', 'SAAG', 450, 'L-2026-0419');
        $this->classificar($c, 450);

        // ---- Entregue, mas o armazém ainda não passou o lote ----
        $c = $this->compra('7326', $boaVista, '2026-08-07', 'RFA_EUDR', 400, 1205, 'POSTO');
        $this->entrega($c, '2026-08-19', 'DINAMO_MACHADO', 400, null);
        $this->classificar($c, 400);

        // ---- Negócio fechado, café ainda não entrou ----
        $this->compra('7330', $tresBarras, '2026-08-08', '4C_RFA', 350);

        // ---- Em estoque, mas sem classificação lançada ----
        $c = $this->compra('7335', $santaClara, '2026-08-10', 'EUDR', 700, 1215, 'POSTO');
        $this->entrega($c, '2026-08-20', 'QUALITE', 700, 'L-2026-0428');

        // ---- Classificada e entregue, só o preço não veio ----
        $c = $this->compra('7338', $rioVerde, '2026-08-11', '4C', 450);
        $this->entrega($c, '2026-08-24', 'SAAG', 450, 'L-2026-0431');
        $this->classificar($c, 450);

        // ---- Entrega parcial: faltam 300 sacas ----
        $c = $this->compra('7341', $boaVista, '2026-08-11', 'SEM_CERT', 550, 1100, 'POSTO');
        $this->entrega($c, '2026-08-25', 'SAAG', 250, 'L-2026-0434');
        $this->classificar($c, 250);

        // ---- Tudo em aberto ----
        $c = $this->compra('7344', $tresBarras, '2026-08-11', 'RFA', 300);
        $this->entrega($c, '2026-08-26', 'QUALITE', 300, null);

        $this->semearContratos();
        $this->semearMensagens();
    }

    private function compra(
        string $uts,
        Fornecedor $fornecedor,
        string $data,
        string $certificacao,
        float $contratado,
        ?float $valorSaca = null,
        ?string $logistica = null,
        string $tipo = 'ARABICA',
        string $padrao = 'FINE_CUP',
        string $bebida = 'DURO',
        ?string $pagamentoObs = null,
    ): Compra {
        $conilon = $tipo === 'CONILON';

        return Compra::create([
            'uts' => 'UTS ' . $uts,
            'data_compra' => $data,
            'fornecedor_id' => $fornecedor->id,
            'certificacao' => $certificacao,
            'logistica' => $logistica,
            'tipo_entrada' => $tipo,
            // Conilon não tem padrão nem bebida (regra de ago/2026).
            'padrao_final' => $conilon ? null : $padrao,
            'tipo_bebida' => $conilon ? null : $bebida,
            'volume_contratado' => $contratado,
            'peso_kg' => Compra::pesoDeSacas($contratado),
            'valor_saca' => $valorSaca,
            'corretor_nome' => $valorSaca === null ? null : 'Corretora Aurora',
            'comissao_pct' => $valorSaca === null ? null : 0.50,
            'pagamento_previsto' => $valorSaca === null ? null : Carbon::parse($data)->addDays(20)->toDateString(),
            'pagamento_obs' => $pagamentoObs,
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * Uma entrada no armazém, com DATA COMPLETA. O peso vem calculado a 60
     * kg/saca, exceto quando informado — aí mostra o caso real de sacas e
     * peso que não fecham exatamente.
     */
    private function entrega(
        Compra $compra,
        string $data,
        string $armazem,
        float $sacas,
        ?string $lote,
        ?float $peso = null,
    ): void {
        $entrega = $compra->entregas()->create([
            'data_entrega' => $data,
            'armazem_id' => $this->armazem($armazem),
            'volume_sacas' => $sacas,
            'peso_kg' => $peso ?? Compra::pesoDeSacas($sacas),
            'numero_lote' => $lote,
            'created_by' => $this->admin->id,
        ]);

        // "Lançada por / em": o dia da entrada, não o dia em que o gerador
        // rodou.
        $entrega->forceFill(['created_at' => Carbon::parse($data . ' 08:40:00')])->saveQuietly();
    }

    /**
     * Distribuição fixa que soma 100% e fecha nas sacas, já com as faixas
     * novas (12 UP e 13 UP). Padrão e bebida vêm da compra — conilon fica
     * sem os dois.
     */
    private function classificar(Compra $compra, float $base): void
    {
        $distribuicao = [
            'peneira_12up' => 10,
            'peneira_13up' => 10,
            'peneira_1718' => 35,
            'peneira_1416' => 25,
            'mercado_interno' => 10,
            'grinders' => 5,
            'moka' => 5,
        ];

        $dados = [
            'compra_id' => $compra->id,
            'padrao_final' => $compra->padrao_final,
            'tipo_bebida' => $compra->tipo_bebida,
            'created_by' => $this->admin->id,
        ];

        foreach ($distribuicao as $faixa => $pct) {
            $dados[$faixa . '_pct'] = $pct;
            $dados[$faixa . '_sacas'] = round($base * $pct / 100, 2);
        }

        Classificacao::create($dados);
    }

    /**
     * Conversa de exemplo no canal da equipe, com duas pessoas e dois dias
     * (para a demo mostrar o separador de data). Mensagens fictícias — a
     * demo é pública.
     */
    private function semearMensagens(): void
    {
        $colega = User::create([
            'role_id' => $this->admin->role_id,
            'name' => 'Marina Alves',
            'email' => 'marina@example.com',
            'password' => Hash::make('demo-nao-usada'),
            'force_password_change' => false,
            'active' => true,
        ]);

        $conversa = [
            ['2026-08-11 08:12:00', $colega, 'Bom dia. NY abriu em alta, +2,15 na Z6.'],
            ['2026-08-11 08:20:00', $this->admin, 'Recebido. Vou segurar a fixação da UT 6013 até a tarde.'],
            ['2026-08-11 15:47:00', $colega, 'SAAG confirmou que não vem mais café da UTS 7322 — dá para liquidar com as 590.'],
            ['2026-08-12 07:58:00', $this->admin, 'Liquidei a 7322. Falta o lote da entrega do DÍNAMO na 7311.'],
            ['2026-08-12 09:05:00', $colega, 'Cobrei o armazém, prometeram passar o número hoje.'],
        ];

        foreach ($conversa as [$quando, $autor, $texto]) {
            Mensagem::create(['user_id' => $autor->id, 'texto' => $texto])
                ->forceFill(['created_at' => $quando, 'updated_at' => $quando])
                ->saveQuietly();
        }

        // O admin da demo abriu o canal antes da última mensagem: assim a
        // página mostra a linha "novas mensagens" e o badge no menu.
        $this->admin->forceFill(['mensagens_lidas_em' => '2026-08-12 08:30:00'])->saveQuietly();
    }

    /**
     * Contratos só para os números do painel (posição e "últimos
     * contratos"); a tela de contratos da demo é mantida à mão.
     * Compradores fictícios — os clientes reais não vão para o ar.
     */
    private function semearContratos(): void
    {
        $qualidade = Qualidade::create([
            'descricao' => 'BRAZIL SANTOS SCREEN 17/18, FINE CUP, NY 2/3, SS, FC',
        ]);

        $northbrook = Cliente::create([
            'nome' => 'NORTHBROOK COFFEE TRADING LLC',
            'endereco' => "120 HARBOR STREET, SUITE 400\nSTAMFORD, CT — USA",
            'ref_padrao' => 'CONTRACT NO. 26-118 DD. 05.08.2026',
        ]);
        $delPuerto = Cliente::create([
            'nome' => 'CAFÉ DEL PUERTO S.A.',
            'endereco' => "AV. DEL PUERTO 1180\nVALENCIA — SPAIN",
        ]);

        Corretora::create(['nome' => 'Corretora Aurora', 'tipo' => 'NOSSA']);

        // 6 lotes, 2 já fixados na Tela NY => badge PARCIAL 2/6.
        $parcial = $this->contrato('6011', '2026-08-05', $northbrook, $qualidade, 102060, 'Z6', '2026-11-01');
        Fixacao::create([
            'contrato_id' => $parcial->id,
            'corretora' => 'Corretora Aurora',
            'tela' => 'Z6',
            'lotes' => 2,
            'level' => 318.50,
            'diferencial' => 34.90,
            'created_by' => $this->admin->id,
        ]);

        // Fixado na mão (FIXED), sem tranches.
        $fixado = $this->contrato('6012', '2026-07-22', $delPuerto, $qualidade, 68040, null, '2026-10-01');
        $fixado->update(['fixado' => true, 'preco_fixado' => 353.40, 'preco_fixado_unidade' => 'CTS_LB', 'diferencial' => null, 'mes_fixacao' => null]);

        // Sem mês de embarque => vira card de pendência no painel.
        // Os kg são escolhidos para dar saca inteira (851 = 51.060 ÷ 60): com
        // meia saca, o "Saldo" do painel fechava 1 acima da subtração que o
        // usuário faz de cabeça entre os dois cards ao lado.
        $this->contrato('6013', '2026-08-08', $northbrook, $qualidade, 51060, 'H7', null);
    }

    private function contrato(
        string $ut,
        string $data,
        Cliente $cliente,
        Qualidade $qualidade,
        float $kg,
        ?string $mesFixacao,
        ?string $embarque,
    ): Contrato {
        return Contrato::create([
            'numero_ut' => $ut,
            'data_contrato' => $data,
            'cliente_id' => $cliente->id,
            'cliente_nome' => $cliente->nome,
            'cliente_endereco' => $cliente->endereco,
            'buyer_ref' => $cliente->ref_padrao,
            'qualidade_id' => $qualidade->id,
            'qualidade_descricao' => $qualidade->descricao,
            'tipo_cafe' => 'ARABICA',
            'certificado' => 'SEM_CERT',
            'quantidade_kg' => $kg,
            'tipo_container' => '40',
            'embalagem' => 'Bulk Liner',
            'diferencial' => $mesFixacao === null ? null : 34.90,
            'mes_fixacao' => $mesFixacao,
            'embarque_mes' => $embarque,
            'incoterms' => 'FOB',
            'porto' => 'SANTOS',
            'created_by' => $this->admin->id,
        ]);
    }

    /* ==================================================================
     * HTML renderizado -> página estática
     * ================================================================== */

    /** Rota (URL absoluta, sem host) => arquivo da demo. */
    private function mapaDeLinks(): array
    {
        $mapa = [
            '/dashboard' => 'inicio.html',
            '/compras' => 'compras.html',
            '/compras/novo' => 'compra-nova.html',
            '/relatorio-compras' => 'relatorio.html',
            '/contratos' => 'contratos.html',
            '/contratos/novo' => 'contrato-novo.html',
            '/tela-ny' => 'tela-ny.html',
            '/mercado' => 'cotacoes.html',
            '/admin/usuarios' => 'usuarios.html',
            '/admin/clientes' => 'clientes.html',
            '/admin/corretoras' => 'corretoras.html',
            '/admin/qualidades' => 'qualidades.html',
            // Sair volta para a tela de login da demo.
            '/logout' => 'index.html',
            '/img/union-trading.png' => 'img/union-trading.png',
        ];

        // Cada compra aponta para a página de detalhe que representa o seu
        // caso; o resto cai na compra com divergência.
        foreach (Compra::all() as $compra) {
            $mapa['/compras/' . $compra->id] = $compra->liquidada() ? 'compra-liquidada.html' : 'compra.html';
        }

        return $mapa;
    }

    private function estatico(string $html): string
    {
        // Token de sessão não vai para arquivo publicado.
        $html = preg_replace('/(name="csrf-token" content=")[^"]*(")/', '$1$2', $html);
        $html = preg_replace('/(name="_token"[^>]*value=")[^"]*(")/', '$1$2', $html);

        // A base vem do APP_URL (aqui, http://localhost:8000) — não escreva
        // "localhost" na mão nos padrões: a porta faz o regex passar batido.
        $base = preg_quote($this->baseDaApp(), '#');

        // A classificação TEM página na demo: o link "Classificar" da tela da
        // compra precisa levar até ela (o motor JS passa a UTS na query).
        $html = preg_replace('#' . $base . '/compras/\d+/classificacao#', 'compra-classificacao.html', $html);

        // As outras sub-rotas de uma compra (editar, financeiro, entregas,
        // liquidar) não têm página. Se o mapa rodasse primeiro, o prefixo
        // virava "compra.html/editar" — link quebrado, que é justamente o
        // defeito que a demo já tinha.
        $html = preg_replace('#' . $base . '/compras/\d+/[^"\'\s]*#', '#', $html);

        // Contrato não tem página de detalhe na demo: cai na lista.
        $html = preg_replace('#' . $base . '/contratos/\d+(/[^"\'\s]*)?#', 'contratos.html', $html);

        // Sub-rotas do Estoque (gerar link assinado, versão pública) — mesmo
        // caso: sem isso o formaction do botão virava "relatorio.html/link".
        $html = preg_replace('#' . $base . '/relatorio-compras/[^"\'\s]*#', '#', $html);

        // Mais longo primeiro: /compras/novo antes de /compras, /compras/12
        // antes de /compras/1.
        $mapa = $this->mapaDeLinks();
        uksort($mapa, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($mapa as $rota => $arquivo) {
            $html = str_replace(url($rota), $arquivo, $html);
        }

        // O que sobrou é tela que a demo não tem (trocar senha, consulta de
        // CNPJ): vira link morto em vez de link quebrado.
        $html = preg_replace('#' . $base . '[^"\'\s]*#', '#', $html);

        return $this->comChromeDaDemo($html);
    }

    private function baseDaApp(): string
    {
        return rtrim(url('/'), '/');
    }

    /** Selo "Demo" e o interceptador de submit, iguais às outras páginas. */
    private function comChromeDaDemo(string $html): string
    {
        $estilo = '<style>.demo-badge{position:fixed;z-index:50;bottom:14px;right:16px;display:flex;align-items:center;'
            . 'gap:7px;height:28px;padding:0 12px;border-radius:20px;font:600 11px/1 monospace;letter-spacing:.12em;'
            . 'text-transform:uppercase;color:#0B3D24;background:rgba(255,255,255,.9);'
            . 'box-shadow:inset 0 0 0 1px rgba(11,61,36,.16),0 4px 14px -6px rgba(11,61,36,.4);text-decoration:none}'
            . '.demo-badge .dot{width:7px;height:7px;border-radius:50%;background:#E8322C}</style>';

        $selo = '<a class="demo-badge" href="https://github.com/fcavichiolle/union-trading">'
            . '<span class="dot"></span>Demo</a>';

        // Favicon inline (grão de café). Sem ele, todas as páginas da demo
        // pediam /favicon.ico e levavam 404 no console — ruído em cima do
        // que a gente quer ver quando algo dá errado de verdade.
        $favicon = '<link rel="icon" href="data:image/svg+xml,'
            . '%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 32 32\'%3E'
            . '%3Crect width=\'32\' height=\'32\' rx=\'6\' fill=\'%230C4028\'/%3E'
            . '%3Cellipse cx=\'16\' cy=\'16\' rx=\'8\' ry=\'11.5\' fill=\'%23E9E2D1\'/%3E'
            . '%3Cpath d=\'M16 5.5c3 6 3 15 0 21\' stroke=\'%230A3A22\' stroke-width=\'2.4\' fill=\'none\'/%3E'
            . '%3C/svg%3E">';

        $estilo = $favicon . $estilo;

        // Formulário sem tratamento do motor não tem para onde postar: só
        // "Sair" navega. O demo-compras.js trata os formulários do módulo de
        // compras ANTES deste (ele para a propagação), então o visitante
        // consegue lançar compra, entrega e classificação de verdade.
        $script = '<script>document.addEventListener("submit",function(e){e.preventDefault();'
            . 'var a=(e.target.getAttribute("action")||"");'
            . 'if(a.indexOf("index.html")>-1)window.location.href="index.html";},true);</script>'
            . '<script src="demo-compras.js"></script>';

        $html = str_replace('</head>', $estilo . '</head>', $html);
        $html = preg_replace('/<body>/', '<body>' . $selo, $html, 1);

        return str_replace('</body>', $script . '</body>', $html);
    }
}
