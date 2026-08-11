<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * O NEGÓCIO fechado: UTS, fornecedor, volume contratado, preço, corretor,
 * pagamento e logística. O que realmente entrou no armazém mora em
 * Entrega — uma compra pode ter várias, em meses e armazéns diferentes.
 */
class Compra extends Model
{
    use HasFactory;

    protected $fillable = [
        'uts',
        'data_compra',
        'fornecedor_id',
        'certificacao',
        'logistica',
        'tipo_entrada',
        'volume_contratado',
        'valor_saca',
        'corretor_nome',
        'comissao_pct',
        'pagamento_previsto',
        'pagamento_obs',
        'created_by',
        // Gravados pelo controller ao liquidar/reabrir, nunca por formulário.
        'liquidada_em',
        'liquidada_por',
    ];

    protected function casts(): array
    {
        return [
            'data_compra' => 'date',
            'pagamento_previsto' => 'date',
            'volume_contratado' => 'decimal:2',
            'valor_saca' => 'decimal:2',
            'comissao_pct' => 'decimal:2',
            'liquidada_em' => 'datetime',
        ];
    }

    // Regra do enunciado: toda nova compra assume "BICA" como tipo de entrada.
    protected $attributes = [
        'tipo_entrada' => 'BICA',
    ];

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class);
    }

    public function liquidadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'liquidada_por');
    }

    public function classificacao(): HasOne
    {
        return $this->hasOne(Classificacao::class);
    }

    public static function armazens(): array
    {
        return ['SAAG' => 'SAAG', 'QUALITE' => 'QUALITÉ', 'DINAMO_MACHADO' => 'DÍNAMO MACHADO'];
    }

    public static function certificacoes(): array
    {
        return [
            'SEM_CERT' => 'Sem certificação',
            '4C' => '4C',
            'RFA' => 'RFA',
            'EUDR' => 'EUDR',
            '4C_EUDR' => '4C + EUDR',
            'RFA_EUDR' => 'RFA + EUDR',
            '4C_RFA' => '4C + RFA',
        ];
    }

    /** POSTO = o vendedor entrega no armazém; RETIRAR = nós buscamos. */
    public static function logisticas(): array
    {
        return ['POSTO' => 'Posto', 'RETIRAR' => 'Retirar'];
    }

    public function logisticaLabel(): ?string
    {
        return $this->logistica === null ? null : (self::logisticas()[$this->logistica] ?? $this->logistica);
    }

    /* ---------- Volumes: contratado × entregue ---------- */

    /**
     * Total efetivamente entregue. Usa o agregado carregado via
     * withSum('entregas as sacas_entregues', 'volume_sacas') quando existir,
     * para não fazer uma query por linha nas listagens.
     */
    public function sacasEntregues(): float
    {
        if (array_key_exists('sacas_entregues', $this->attributes)) {
            return (float) $this->attributes['sacas_entregues'];
        }

        return (float) $this->entregas()->sum('volume_sacas');
    }

    /**
     * Quanto ainda falta entregar. Pode ser negativo quando o armazém
     * recebe mais do que o contratado — a diferença é informação, não erro
     * (ver saldoDivergente()).
     */
    public function saldoAEntregar(): float
    {
        return round((float) $this->volume_contratado - $this->sacasEntregues(), 2);
    }

    public function totalmenteEntregue(): bool
    {
        return $this->sacasEntregues() > 0 && abs($this->saldoAEntregar()) < 0.01;
    }

    /** Entregou mais do que o contratado. */
    public function entregouAMais(): bool
    {
        return $this->saldoAEntregar() < -0.01;
    }

    /* ---------- Liquidação ---------- */

    /**
     * Compra encerrada com o volume que realmente entrou. Enquanto não é
     * liquidada, uma diferença pode significar café ainda a receber — por
     * isso o aviso continua na tela.
     */
    public function liquidada(): bool
    {
        return $this->liquidada_em !== null;
    }

    /** Tem diferença entre contratado e entregue, e ninguém decidiu ainda. */
    public function divergenciaPendente(): bool
    {
        return ! $this->liquidada() && abs($this->saldoAEntregar()) > 0.01;
    }

    /** Só faz sentido liquidar o que já teve alguma entrada no armazém. */
    public function podeLiquidar(): bool
    {
        return ! $this->liquidada() && $this->sacasEntregues() > 0;
    }

    /**
     * O volume que o sistema reconhece como final: o entregue, quando
     * liquidada; o contratado enquanto a compra segue em aberto.
     */
    public function volumeReconhecido(): float
    {
        return $this->liquidada() ? $this->sacasEntregues() : (float) $this->volume_contratado;
    }

    public function scopeNaoLiquidadas(Builder $query): Builder
    {
        return $query->whereNull('liquidada_em');
    }

    /* ---------- Financeiro (dados da negociação) ---------- */

    /** Valor do negócio como foi fechado. */
    public function valorContratado(): ?float
    {
        return $this->valor_saca === null
            ? null
            : round((float) $this->valor_saca * (float) $this->volume_contratado, 2);
    }

    /** Valor do que realmente entrou — é por ele que se paga. */
    public function valorEntregue(): ?float
    {
        return $this->valor_saca === null
            ? null
            : round((float) $this->valor_saca * $this->sacasEntregues(), 2);
    }

    /* ---------- Scopes de pendência ---------- */

    /**
     * Ainda falta volume a entregar — e a compra não foi liquidada. Depois
     * de liquidada não há mais saldo: o que entrou é o que valeu.
     */
    public function scopeComSaldoAEntregar(Builder $query): Builder
    {
        return $query->naoLiquidadas()->whereRaw(
            'volume_contratado > (select coalesce(sum(volume_sacas), 0) from entregas where entregas.compra_id = compras.id)'
        );
    }

    public function scopeSemPreco(Builder $query): Builder
    {
        return $query->whereNull('valor_saca');
    }

    /** Compra com qualquer etapa em aberto (classificação, preço ou saldo). */
    public function scopeComPendencia(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereDoesntHave('classificacao')
                ->orWhere(fn (Builder $s) => $s->semPreco())
                ->orWhere(fn (Builder $s) => $s->comSaldoAEntregar())
                ->orWhereHas('entregas', fn ($e) => $e->semNumeroLote());
        });
    }
}
