<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MercadoTest extends TestCase
{
    use RefreshDatabase;

    private User $diretoria;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['slug' => 'diretoria', 'nome' => 'Diretoria']);
        $this->diretoria = User::create([
            'role_id' => $role->id, 'name' => 'Dir', 'email' => 'dir@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);

        Cache::flush();
    }

    /** JSON no formato do endpoint v8/finance/chart do Yahoo. */
    private function chartJson(float $price, float $previousClose): array
    {
        return [
            'chart' => [
                'result' => [[
                    'meta' => [
                        'regularMarketPrice' => $price,
                        'chartPreviousClose' => $previousClose,
                    ],
                    'indicators' => [
                        'quote' => [[
                            'open' => [null, $price - 1.0],
                            'high' => [$price + 2.5, null],
                            'low' => [$price - 3.0],
                            'close' => [$price],
                        ]],
                    ],
                ]],
            ],
        ];
    }

    public function test_api_market_devolve_o_formato_esperado(): void
    {
        Http::fake(['query1.finance.yahoo.com/*' => Http::response($this->chartJson(335.65, 335.55))]);

        $resp = $this->actingAs($this->diretoria)->getJson(route('mercado.api'));

        $resp->assertOk()
            ->assertJsonStructure([
                'updated_at',
                'cambio' => ['dolar' => ['value', 'dif'], 'euro' => ['value', 'dif']],
                'arabica' => [['code', 'month', 'price', 'dif', 'max', 'min', 'open', 'close']],
                'robusta' => [['code', 'month', 'price', 'dif', 'max', 'min', 'open', 'close']],
            ]);

        $kc = $resp->json('arabica.0');
        $this->assertSame('KCU6', $kc['code']);
        $this->assertEqualsWithDelta(335.65, $kc['price'], 0.001);
        $this->assertEqualsWithDelta(0.10, $kc['dif'], 0.001);      // price - previousClose
        $this->assertEqualsWithDelta(338.15, $kc['max'], 0.001);    // último valor não nulo da série high
        $this->assertEqualsWithDelta(334.65, $kc['open'], 0.001);
    }

    public function test_falha_do_yahoo_cai_no_ultimo_valor_conhecido(): void
    {
        // Fake único com flag: stubs de chamadas sucessivas de Http::fake se
        // acumulam (o primeiro match vence), então alternamos via closure.
        $foraDoAr = false;
        Http::fake(function () use (&$foraDoAr) {
            return $foraDoAr
                ? Http::response(null, 500)
                : Http::response($this->chartJson(335.65, 335.55));
        });

        // 1ª chamada: tudo OK — grava o "último valor conhecido" por símbolo.
        $this->actingAs($this->diretoria)->getJson(route('mercado.api'))->assertOk();

        // 2ª chamada: Yahoo fora do ar — deve responder com stale=true, sem 500.
        $foraDoAr = true;
        Cache::forget('mercado.snapshot'); // força novo fetch (o TTL de 30s ainda não venceu)

        $resp = $this->actingAs($this->diretoria)->getJson(route('mercado.api'));

        $resp->assertOk();
        $this->assertEqualsWithDelta(335.65, $resp->json('arabica.0.price'), 0.001);
        $this->assertTrue($resp->json('arabica.0.stale'));
    }

    public function test_posicao_sem_dado_algum_sai_como_indisponivel(): void
    {
        Http::fake(['query1.finance.yahoo.com/*' => Http::response(null, 404)]);

        $resp = $this->actingAs($this->diretoria)->getJson(route('mercado.api'));

        $resp->assertOk();
        $this->assertNull($resp->json('arabica.0.price'));
        $this->assertNull($resp->json('cambio.dolar.value'));
    }

    public function test_pagina_de_cotacoes_abre_para_diretoria(): void
    {
        Http::fake(['query1.finance.yahoo.com/*' => Http::response($this->chartJson(335.65, 335.55))]);

        $this->actingAs($this->diretoria)->get(route('mercado.index'))
            ->assertOk()
            ->assertSee('Arábica — ICE New York')
            ->assertSee('Robusta — ICE Londres')
            ->assertSee('KCU6');
    }

    public function test_visitante_nao_logado_nao_acessa_a_api(): void
    {
        $this->getJson(route('mercado.api'))->assertUnauthorized();
    }
}
