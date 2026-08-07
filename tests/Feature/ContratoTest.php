<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Qualidade;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ContratoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Cliente $cliente;
    private Qualidade $qualidade;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['slug' => 'admin', 'nome' => 'Administrador']);
        $this->admin = User::create([
            'role_id' => $role->id, 'name' => 'Admin', 'email' => 'admin@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);
        $this->cliente = Cliente::create(['nome' => 'ICONA', 'endereco' => "INICIATIVAS COMERCIALES NAVARRAS S.A\nMadrid – Spain"]);
        $this->qualidade = Qualidade::create(['descricao' => 'CAFÉ ARÁB NAT BRASIL CRIBA 14/16 SS GC']);
    }

    private function novoContrato(array $overrides = []): Contrato
    {
        return Contrato::create(array_merge([
            'numero_ut' => '5940', 'data_contrato' => '2026-08-05',
            'cliente_id' => $this->cliente->id, 'cliente_nome' => $this->cliente->nome, 'cliente_endereco' => $this->cliente->endereco,
            'qualidade_id' => $this->qualidade->id, 'qualidade_descricao' => $this->qualidade->descricao,
            'tipo_cafe' => 'ARABICA', 'certificado' => 'RFA_EUDR', 'quantidade_kg' => 108000,
            'tipo_container' => '40', 'embalagem' => 'Bulk Liner', 'incoterms' => 'FOB', 'porto' => 'SANTOS',
            'created_by' => $this->admin->id,
        ], $overrides));
    }

    public function test_arabica_calcula_sacas_lotes_e_containers(): void
    {
        $c = $this->novoContrato(); // 108.000 kg, arábica, 40'

        $this->assertEqualsWithDelta(1800, (float) $c->sacas, 0.01);   // 108000 / 60
        $this->assertSame(6, $c->lotes);                                // round(1800 / 283.49) = round(6,35) = 6
        $this->assertSame(5, $c->containers);                           // ceil(108000 / 25000)
        $this->assertEqualsWithDelta(21600, (float) $c->kg_por_container, 0.01);
    }

    public function test_conilon_usa_divisor_166_66(): void
    {
        $c = $this->novoContrato(['tipo_cafe' => 'CONILON', 'quantidade_kg' => 100000]);

        // sacas = 100000/60 = 1666,67 ; lotes = round(1666,67/166,66) = round(10,0004) = 10
        $this->assertSame(10, $c->lotes);
    }

    public function test_lotes_arredondam_para_cima_a_partir_de_meio(): void
    {
        // sacas/283,49 ~ 1,51 -> arredonda para 2
        $c = $this->novoContrato(['quantidade_kg' => 25700]); // 428,33 sacas / 283,49 = 1,511
        $this->assertSame(2, $c->lotes);
    }

    public function test_container_20_pes_usa_capacidade_22000(): void
    {
        $c = $this->novoContrato(['tipo_container' => '20', 'quantidade_kg' => 100000]);

        $this->assertSame(5, $c->containers);                            // ceil(100000/22000) = 5
        $this->assertEqualsWithDelta(20000, (float) $c->kg_por_container, 0.01);
    }

    public function test_valores_calculados_ignoram_dados_forjados(): void
    {
        $c = $this->novoContrato();
        $c->lotes = 999;
        $c->containers = 999;
        $c->save();
        $c->refresh();

        $this->assertSame(6, $c->lotes);
        $this->assertSame(5, $c->containers);
    }

    public function test_linha_de_quantidade_formatada(): void
    {
        $c = $this->novoContrato();
        $this->assertSame('108.000 kilos – 21,6 ton each container (5 container(s))', $c->quantidadeLinha());
    }

    public function test_embalagem_jute_bags_59kg_usa_saca_de_59(): void
    {
        $c = $this->novoContrato(['embalagem' => 'Jute Bags 59kg', 'quantidade_kg' => 59000]);

        $this->assertSame(59, $c->kgPorSaca());
        $this->assertEqualsWithDelta(1000, (float) $c->sacas, 0.01); // 59000 / 59
    }

    public function test_preco_santos_usa_ny_ice(): void
    {
        $c = $this->novoContrato(['porto' => 'SANTOS', 'diferencial' => '-16.00', 'mes_fixacao' => 'Z6']);

        $this->assertSame(
            'To be fixed at sellers call at -16.00 cents/pounds under 6 lot(s) Z6 NY ICE. '
                . 'Fixation to be done prior to invoicing or to first notice day, whichever is earlier.',
            $c->precoLinha()
        );
    }

    public function test_preco_vitoria_usa_robusta_de_londres(): void
    {
        // 50.000 kg conilon -> 833,33 sacas / 166,66 = 5 lotes
        $c = $this->novoContrato([
            'porto' => 'VITORIA', 'tipo_cafe' => 'CONILON', 'quantidade_kg' => 50000,
            'diferencial' => '-65', 'mes_fixacao' => 'Sep_2026',
        ]);

        $this->assertSame(5, $c->lotes);
        $this->assertSame(
            'To be fixed at sellers call at -65 USD/MT of ICE ROBUSTA CF LONDON, 5 lot(s) x Sep_2026. '
                . 'Fixation to be done prior to invoicing or to first notice day, whichever is earlier.',
            $c->precoLinha()
        );
    }

    public function test_store_cria_contrato_e_calcula_lotes(): void
    {
        $resposta = $this->actingAs($this->admin)->post(route('contratos.store'), [
            'numero_ut' => '5941', 'data_contrato' => '2026-08-05',
            'cliente_id' => $this->cliente->id, 'buyer_ref' => '31722',
            'qualidade_id' => $this->qualidade->id, 'tipo_cafe' => 'ARABICA',
            'certificado' => 'RFA_EUDR', 'quantidade_kg' => 108000,
            'tipo_container' => '40', 'embalagem' => 'Bulk Liner',
            'diferencial' => '-16.00', 'mes_fixacao' => 'Z6', 'embarque_mes' => '2026-09',
            'incoterms' => 'FOB', 'porto' => 'SANTOS', 'remarks' => 'SHIPMENT 01/09',
        ]);

        $contrato = Contrato::where('numero_ut', '5941')->firstOrFail();
        $resposta->assertRedirect(route('contratos.show', $contrato));
        $this->assertSame(6, $contrato->lotes);
        $this->assertSame('ICONA', $contrato->cliente_nome); // snapshot gravado
    }

    public function test_numero_ut_e_unico(): void
    {
        $this->novoContrato(['numero_ut' => '5940']);

        $resposta = $this->actingAs($this->admin)->post(route('contratos.store'), [
            'numero_ut' => '5940', 'data_contrato' => '2026-08-05',
            'cliente_id' => $this->cliente->id, 'qualidade_id' => $this->qualidade->id,
            'tipo_cafe' => 'ARABICA', 'certificado' => 'RFA_EUDR', 'quantidade_kg' => 50000,
            'tipo_container' => '40', 'embalagem' => 'Bulk Liner', 'incoterms' => 'FOB', 'porto' => 'SANTOS',
        ]);

        $resposta->assertSessionHasErrors('numero_ut');
    }

    public function test_pdf_e_gerado(): void
    {
        $c = $this->novoContrato();

        $resposta = $this->actingAs($this->admin)->get(route('contratos.pdf', $c));

        $resposta->assertOk();
        $this->assertStringContainsString('application/pdf', $resposta->headers->get('content-type'));
    }

    public function test_nome_do_arquivo_pdf(): void
    {
        $c = $this->novoContrato(['numero_ut' => '5940', 'data_contrato' => '2026-08-05']);

        $this->assertSame('UT_5940_ICONA_05-08-2026.pdf', $c->nomeArquivoPdf());
    }
}
