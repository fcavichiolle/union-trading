<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Contrato extends Model
{
    use HasFactory;

    protected $table = 'contratos';

    /** Divisores de lote por tipo de café e kg por saca (regras do negócio). */
    public const DIVISOR_ARABICA = 283.49;
    public const DIVISOR_CONILON = 166.66;
    public const KG_POR_SACA = 60;

    /** Capacidade máxima (kg) por tipo de container — define o nº de containers. */
    public const CAPACIDADE_CONTAINER = ['20' => 22000, '40' => 25000];

    protected $fillable = [
        'numero_ut', 'data_contrato',
        'cliente_id', 'cliente_nome', 'cliente_endereco', 'buyer_ref',
        'qualidade_id', 'qualidade_descricao', 'tipo_cafe', 'certificado',
        'quantidade_kg', 'tipo_container', 'embalagem',
        'diferencial', 'mes_fixacao', 'fixado', 'preco_fixado', 'preco_fixado_unidade',
        'embarque_mes', 'incoterms', 'porto', 'remarks',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'data_contrato' => 'date',
            'embarque_mes' => 'date',
            'quantidade_kg' => 'decimal:2',
            'kg_por_container' => 'decimal:2',
            'sacas' => 'decimal:2',
            'fixado' => 'boolean',
            'preco_fixado' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Sacas, lotes, containers e peso por container são SEMPRE
        // recalculados no servidor a partir de quantidade_kg + tipo_cafe +
        // tipo_container — nunca aceitos do formulário.
        static::saving(function (Contrato $c) {
            $kg = (float) $c->quantidade_kg;

            $c->sacas = round($kg / $c->kgPorSaca(), 2);

            $divisor = $c->tipo_cafe === 'CONILON' ? self::DIVISOR_CONILON : self::DIVISOR_ARABICA;
            // Lotes arredondados (1,51 -> 2), conforme regra dos contratos.
            $c->lotes = (int) round($c->sacas / $divisor);

            $capacidade = self::CAPACIDADE_CONTAINER[$c->tipo_container] ?? self::CAPACIDADE_CONTAINER['40'];
            $c->containers = max(1, (int) ceil($kg / $capacidade));
            $c->kg_por_container = round($kg / $c->containers, 2);
        });
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function qualidade(): BelongsTo
    {
        return $this->belongsTo(Qualidade::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Peso da saca no cálculo: 59 kg para "Jute Bags 59kg", 60 kg no resto. */
    public function kgPorSaca(): int
    {
        return $this->embalagem === 'Jute Bags 59kg' ? 59 : self::KG_POR_SACA;
    }

    /** Nome sugerido do arquivo PDF, ex.: UT_5940_ICONA_05-08-2026.pdf */
    public function nomeArquivoPdf(): string
    {
        $slug = Str::of($this->cliente_nome)->upper()->replaceMatches('/[^A-Z0-9]+/', '')->limit(20, '');
        $num = preg_replace('/\D+/', '', $this->numero_ut) ?: $this->numero_ut;

        return "UT_{$num}_{$slug}_" . $this->data_contrato->format('d-m-Y') . '.pdf';
    }

    /* ---------- Listas fixas (dropdowns) ---------- */

    /** @return array<string,string> código => rótulo */
    public static function certificados(): array
    {
        return [
            'SEM_CERT' => 'Sem certificado',
            '4C' => '4C',
            'EUDR' => 'EUDR',
            'RFA' => 'RFA',
            '4C_EUDR' => '4C + EUDR',
            'RFA_EUDR' => 'RFA + EUDR',
        ];
    }

    public static function embalagens(): array
    {
        return ['Bulk Liner', 'Jute Bags', 'Jute Bags 59kg', 'Big Bag', 'Jute + Grainpro'];
    }

    /** Só trabalhamos com FOB. Incoterm => nome por extenso (para o PDF). */
    public static function incotermsLista(): array
    {
        return ['FOB' => 'Free on board'];
    }

    public static function portos(): array
    {
        return ['SANTOS' => 'Santos', 'VITORIA' => 'Vitória'];
    }

    /** Meses NY ICE (arábica) — usados quando o porto é Santos. Valor = código (ex.: Z6). */
    public static function mesesFixacaoSantos(): array
    {
        $letras = ['H' => 'Março', 'K' => 'Maio', 'N' => 'Julho', 'U' => 'Setembro', 'Z' => 'Dezembro'];
        $opcoes = [];
        foreach (['6', '7', '8'] as $ano) {
            foreach ($letras as $cod => $mes) {
                $opcoes["{$cod}{$ano}"] = "{$cod}{$ano} ({$mes}/202{$ano}) · NY ICE";
            }
        }
        return $opcoes;
    }

    /** Meses ICE Robusta de Londres — usados quando o porto é Vitória. Valor = texto do contrato (ex.: Sep_2026). */
    public static function mesesFixacaoVitoria(): array
    {
        $meses = ['Jan', 'Mar', 'May', 'Jul', 'Sep', 'Nov'];
        $opcoes = [];
        foreach (['2026', '2027', '2028'] as $ano) {
            foreach ($meses as $m) {
                $opcoes["{$m}_{$ano}"] = "{$m}/{$ano} · Londres";
            }
        }
        return $opcoes;
    }

    /** União das duas listas (usada na validação). */
    public static function mesesFixacao(): array
    {
        return self::mesesFixacaoSantos() + self::mesesFixacaoVitoria();
    }

    /**
     * Unidades disponíveis para o preço FIXED. É uma escolha livre (valor
     * negociado entre as partes) — não depende do porto do contrato, ao
     * contrário da unidade "oficial" da bolsa usada na fórmula "a fixar"
     * (ver unidadePreco()).
     *
     * @return array<string,string>
     */
    public static function unidadesPreco(): array
    {
        return ['CTS_LB' => 'cts/lb', 'USD_MT' => 'USD/MT'];
    }

    /* ---------- Strings formatadas para o PDF ---------- */

    public function certificadoLabel(): string
    {
        return self::certificados()[$this->certificado] ?? $this->certificado;
    }

    /** "108.000 kilos – 21,6 ton each container (5 container(s))" */
    public function quantidadeLinha(): string
    {
        $kg = number_format((float) $this->quantidade_kg, 0, ',', '.');
        $ton = $this->formatarNumero((float) $this->kg_por_container / 1000);

        return "{$kg} kilos – {$ton} ton each container ({$this->containers} container(s))";
    }

    /**
     * Unidade da bolsa de referência, conforme o porto: Santos usa
     * cents/pounds (arábica NY ICE), Vitória usa USD/MT (Robusta de
     * Londres). Usada só na fórmula "a fixar" — o preço FIXED tem unidade
     * própria, escolhida livremente (ver preco_fixado_unidade).
     */
    public function unidadePreco(): string
    {
        return $this->porto === 'VITORIA' ? 'USD/MT' : 'cts/lb';
    }

    /**
     * Linha de preço. Se o contrato já está FIXED, mostra o valor
     * absoluto acordado na unidade escolhida (ex.: "353,40 cts/lb" ou
     * "3.725,00 USD/MT" — independente do porto). Se ainda é a fixar,
     * mostra a fórmula com o nº de lotes calculado — o texto muda
     * conforme o porto: Santos usa NY ICE (arábica, cents/pounds);
     * Vitória usa ICE Robusta de Londres (USD/MT).
     */
    public function precoLinha(): string
    {
        if ($this->fixado) {
            $unidade = self::unidadesPreco()[$this->preco_fixado_unidade] ?? $this->unidadePreco();

            return number_format((float) $this->preco_fixado, 2, ',', '.') . ' ' . $unidade;
        }

        $sufixo = 'Fixation to be done prior to invoicing or to first notice day, whichever is earlier.';

        if ($this->porto === 'VITORIA') {
            return "To be fixed at sellers call at {$this->diferencial} USD/MT of ICE ROBUSTA CF LONDON, "
                . "{$this->lotes} lot(s) x {$this->mes_fixacao}. {$sufixo}";
        }

        return "To be fixed at sellers call at {$this->diferencial} cents/pounds under "
            . "{$this->lotes} lot(s) {$this->mes_fixacao} NY ICE. {$sufixo}";
    }

    /** "Sep_2026 (Shipment from 01/09/2026 to 30/09/2026)" */
    public function embarqueLinha(): ?string
    {
        if (! $this->embarque_mes) {
            return null;
        }
        $ini = $this->embarque_mes->copy()->startOfMonth();
        $fim = $this->embarque_mes->copy()->endOfMonth();
        $abrev = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $mesTxt = $abrev[$ini->month - 1] . '_' . $ini->year;

        return "{$mesTxt} (Shipment from {$ini->format('d/m/Y')} to {$fim->format('d/m/Y')})";
    }

    /** 'FOB "Free on board" – Santos' */
    public function incotermsLinha(): string
    {
        $ext = self::incotermsLista()[$this->incoterms] ?? '';
        $porto = self::portos()[$this->porto] ?? $this->porto;

        return trim("{$this->incoterms} \"{$ext}\" – {$porto}");
    }

    /** Formata número com vírgula decimal, sem zeros/vírgula sobrando (21,6 / 20 / 19,2). */
    private function formatarNumero(float $valor): string
    {
        $txt = number_format($valor, 2, ',', '.');
        $txt = rtrim($txt, '0');
        return rtrim($txt, ',');
    }
}
