<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        // Cancelamento é sempre gravado pelo controller (nunca vem de
        // formulário de contrato), mas fica preenchível para os testes.
        'cancelado_em', 'motivo_cancelamento', 'cancelado_por',
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
            'cancelado_em' => 'datetime',
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

    public function fixacoes(): HasMany
    {
        return $this->hasMany(Fixacao::class);
    }

    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por');
    }

    /* ---------- Cancelamento ---------- */

    public function cancelado(): bool
    {
        return $this->cancelado_em !== null;
    }

    /**
     * Contratos que ainda valem. Cancelado continua no sistema (histórico),
     * mas sai da posição: não aparece na Tela NY nem entra nos números do
     * painel — não há mais o que fixar nem o que embarcar.
     */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->whereNull('cancelado_em');
    }

    /* ---------- Fixação por lotes (Tela NY) ---------- */

    /**
     * Lotes já fixados na Tela NY. Usa o agregado carregado via
     * withSum('fixacoes as lotes_fixados', 'lotes') quando disponível
     * (listagens, sem N+1); senão consulta direto.
     */
    public function lotesFixados(): int
    {
        if (array_key_exists('lotes_fixados', $this->attributes)) {
            return (int) $this->attributes['lotes_fixados'];
        }

        return (int) $this->fixacoes()->sum('lotes');
    }

    public function lotesRestantes(): int
    {
        return max(0, (int) $this->lotes - $this->lotesFixados());
    }

    /** Tem tranche registrada, mas ainda não fechou todos os lotes. */
    public function parcialmenteFixado(): bool
    {
        return ! $this->fixado && $this->lotesFixados() > 0;
    }

    /**
     * Recalcula o estado de fixação a partir das tranches (chamado ao
     * registrar/excluir uma fixação na Tela NY). Completou todos os lotes
     * → vira FIXED com preço = média ponderada (por lotes) das tranches,
     * na unidade da bolsa do porto. Ficou incompleto (tranche excluída)
     * → volta a A FIXAR. Contratos criados manualmente como FIXED não têm
     * tranches e nunca passam por aqui, então não são afetados.
     */
    public function recalcularFixacao(): void
    {
        $tranches = $this->fixacoes()->get();
        $lotesFixados = (int) $tranches->sum('lotes');

        if ($lotesFixados >= (int) $this->lotes && $lotesFixados > 0) {
            $soma = $tranches->sum(fn (Fixacao $f) => $f->lotes * (float) $f->preco);

            $this->fixado = true;
            $this->preco_fixado = round($soma / $lotesFixados, 2);
            $this->preco_fixado_unidade = $this->porto === 'VITORIA' ? 'USD_MT' : 'CTS_LB';
        } else {
            $this->fixado = false;
            $this->preco_fixado = null;
            $this->preco_fixado_unidade = null;
        }

        $this->save();
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

    /* ==================================================================
     * Posições de bolsa (telas)
     * ==================================================================
     *
     * As listas são CALCULADAS a partir da data, não escritas à mão: a
     * posição que já venceu sai sozinha e o ano novo entra sozinho. Antes
     * eram três anos fixos ('6','7','8'), o que obrigava a editar código
     * todo janeiro — e deixava H6/K6/N6 na tela depois de vencidas.
     *
     * São DUAS listas de propósito:
     *  - `mesesFixacao*()`      → posições EM ABERTO, para os formulários;
     *  - `mesesFixacao*Todas()` → janela larga (anos para trás também),
     *    para VALIDAÇÃO e para exibir rótulo de contrato antigo. Sem isso,
     *    editar um contrato fixado numa posição vencida passaria a dar
     *    "mês inválido", e a Tela NY perderia o rótulo do histórico.
     */

    /** Meses de entrega na NY ICE (arábica): letra do código => nº do mês. */
    private const MESES_NY = ['H' => 3, 'K' => 5, 'N' => 7, 'U' => 9, 'Z' => 12];

    /**
     * Meses da ICE Robusta (Londres). Não existe dezembro no robusta — os
     * vencimentos são F/H/K/N/U/X (X = novembro).
     */
    private const MESES_LONDRES = ['Jan' => 1, 'Mar' => 3, 'May' => 5, 'Jul' => 7, 'Sep' => 9, 'Nov' => 11];

    /** Quantos anos de posições futuras oferecer nos formulários. */
    private const ANOS_A_FRENTE = 3;

    /** Quantos anos para trás continuar aceitando (contratos e fixações antigas). */
    private const ANOS_ATRAS = 4;

    /** Meses NY ICE em aberto — usados quando o porto é Santos (ex.: Z6). */
    public static function mesesFixacaoSantos(): array
    {
        return self::posicoesSantos(now()->year, self::ANOS_A_FRENTE, apenasEmAberto: true);
    }

    /** Janela larga da NY, para validação e rótulo de posição vencida. */
    public static function mesesFixacaoSantosTodas(): array
    {
        return self::posicoesSantos(now()->year - self::ANOS_ATRAS, self::ANOS_ATRAS + self::ANOS_A_FRENTE, apenasEmAberto: false);
    }

    /** Meses ICE Robusta em aberto — porto Vitória (ex.: Sep_2026). */
    public static function mesesFixacaoVitoria(): array
    {
        return self::posicoesVitoria(now()->year, self::ANOS_A_FRENTE, apenasEmAberto: true);
    }

    /** Janela larga de Londres, para validação e rótulo. */
    public static function mesesFixacaoVitoriaTodas(): array
    {
        return self::posicoesVitoria(now()->year - self::ANOS_ATRAS, self::ANOS_ATRAS + self::ANOS_A_FRENTE, apenasEmAberto: false);
    }

    /** União das listas em aberto (formulários). */
    public static function mesesFixacao(): array
    {
        return self::mesesFixacaoSantos() + self::mesesFixacaoVitoria();
    }

    /** União da janela larga (VALIDAÇÃO — aceita o histórico). */
    public static function mesesFixacaoTodas(): array
    {
        return self::mesesFixacaoSantosTodas() + self::mesesFixacaoVitoriaTodas();
    }

    /**
     * Rótulo de uma tela ("Z6" => "Z6 (Dezembro/2026) · NY ICE"), servindo
     * também para posição vencida. Devolve o próprio código quando não
     * reconhece — melhor mostrar o código do que uma linha vazia.
     */
    public static function rotuloDaTela(?string $codigo): ?string
    {
        if ($codigo === null || $codigo === '') {
            return null;
        }

        return self::mesesFixacaoTodas()[$codigo] ?? $codigo;
    }

    /** A tela é de Londres? (o código de lá tem o ano inteiro: Sep_2026) */
    public static function telaEhDeLondres(string $codigo): bool
    {
        return array_key_exists($codigo, self::mesesFixacaoVitoriaTodas());
    }

    /**
     * @return array<string,string> código => rótulo, em ordem de vencimento
     */
    private static function posicoesSantos(int $anoInicial, int $anos, bool $apenasEmAberto): array
    {
        $nomes = [3 => 'Março', 5 => 'Maio', 7 => 'Julho', 9 => 'Setembro', 12 => 'Dezembro'];
        $opcoes = [];

        foreach (self::anos($anoInicial, $anos) as $ano) {
            foreach (self::MESES_NY as $letra => $mes) {
                if ($apenasEmAberto && self::venceu($ano, $mes)) {
                    continue;
                }

                // O código da bolsa usa só o ÚLTIMO dígito do ano (H7 = 2027).
                $codigo = $letra . substr((string) $ano, -1);
                $opcoes[$codigo] = "{$codigo} ({$nomes[$mes]}/{$ano}) · NY ICE";
            }
        }

        return $opcoes;
    }

    /** @return array<string,string> */
    private static function posicoesVitoria(int $anoInicial, int $anos, bool $apenasEmAberto): array
    {
        $opcoes = [];

        foreach (self::anos($anoInicial, $anos) as $ano) {
            foreach (self::MESES_LONDRES as $sigla => $mes) {
                if ($apenasEmAberto && self::venceu($ano, $mes)) {
                    continue;
                }

                $opcoes["{$sigla}_{$ano}"] = "{$sigla}/{$ano} · Londres";
            }
        }

        return $opcoes;
    }

    /** @return array<int,int> */
    private static function anos(int $inicial, int $quantos): array
    {
        return range($inicial, $inicial + $quantos);
    }

    /**
     * A posição já passou? Considera vencida quando o MÊS de entrega ficou
     * para trás — dentro do próprio mês do vencimento a posição ainda
     * aparece, porque ainda se negocia nela.
     */
    private static function venceu(int $ano, int $mes): bool
    {
        $hoje = now();

        return $ano < $hoje->year || ($ano === $hoje->year && $mes < $hoje->month);
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
        // "Sep/2026". Era "Sep_2026" — o underline saiu por pedido da mesa
        // (12/ago/2026), junto com os do cabeçalho. Atenção: NÃO confundir
        // com o código da posição de Londres (`mes_fixacao`, tipo Sep_2026),
        // que é formato de bolsa e continua com underline.
        $mesTxt = $abrev[$ini->month - 1] . '/' . $ini->year;

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
