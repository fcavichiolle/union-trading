<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Classificacao extends Model
{
    use HasFactory;

    protected $table = 'classificacoes';

    /** Divisor fixo do enunciado para cálculo de lotes. */
    public const SACAS_POR_LOTE = 283.49;

    protected $fillable = [
        'compra_id',
        'padrao_final',
        'tipo_bebida',
        'created_by',
        // As colunas das faixas entram em getFillable(), a partir de faixas().
    ];

    /**
     * Cada faixa tem duas colunas (_pct e _sacas). Derivar de faixas() em
     * vez de listar à mão garante que faixa nova já nasça preenchível.
     */
    public function getFillable(): array
    {
        $fillable = parent::getFillable();

        foreach (array_keys(self::faixas()) as $faixa) {
            $fillable[] = $faixa . '_pct';
            $fillable[] = $faixa . '_sacas';
        }

        return $fillable;
    }

    protected function casts(): array
    {
        $casts = ['quantidade_lotes' => 'decimal:4'];

        foreach (array_keys(self::faixas()) as $faixa) {
            $casts[$faixa . '_pct'] = 'decimal:2';
            $casts[$faixa . '_sacas'] = 'decimal:2';
        }

        return $casts;
    }

    protected static function booted(): void
    {
        // Cálculo automático de lotes: SEMPRE recalculado no servidor a
        // partir da soma das sacas, nunca aceito como valor vindo do form.
        static::saving(function (Classificacao $classificacao) {
            $classificacao->quantidade_lotes = round(
                $classificacao->totalSacas() / self::SACAS_POR_LOTE,
                4
            );
        });
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Faixas da distribuição, na ordem em que aparecem na tela
     * (prefixo da coluna => rótulo).
     *
     * LISTA CENTRAL: o model (soma e lotes), a validação
     * (StoreClassificacaoRequest), o SQL do Estoque
     * (DashboardController::distribuicao) e as tabelas de exibição todas
     * leem daqui. Antes cada faixa nova precisava ser somada à mão em
     * quatro lugares — e esquecer um deles dava número errado calado.
     *
     * @return array<string,string> prefixo => rótulo
     */
    public static function faixas(): array
    {
        return [
            'peneira_12up' => 'SCS 12 UP',
            'peneira_13up' => 'SCS 13 UP',
            'peneira_1718' => 'SCS 17/18',
            'peneira_1416' => 'SCS 14/16',
            'mercado_interno' => 'Mercado interno',
            'grinders' => 'Grinders',
            'moka' => 'Moka',
        ];
    }

    /** Soma das sacas de todas as faixas. */
    public function totalSacas(): float
    {
        $total = 0.0;

        foreach (array_keys(self::faixas()) as $faixa) {
            $total += (float) $this->{$faixa . '_sacas'};
        }

        return $total;
    }

    /** Soma das porcentagens informadas (deve fechar 100%). */
    public function totalPct(): float
    {
        $total = 0.0;

        foreach (array_keys(self::faixas()) as $faixa) {
            $total += (float) $this->{$faixa . '_pct'};
        }

        return $total;
    }

    /**
     * Padrões finais de classificação (código => rótulo). Lista central:
     * alimenta o dropdown, a validação, os filtros e a exibição.
     * A coluna é VARCHAR (deixou de ser ENUM na migration
     * ..._padroes_novos_e_peneiras_12up_13up), então acrescentar um padrão
     * aqui é suficiente — sem migration de ALTER, e testável no SQLite.
     *
     * @return array<string,string>
     */
    public static function padroes(): array
    {
        return [
            'FINE_CUP' => 'Fine Cup',
            'GOOD_CUP' => 'Good Cup',
            'VERY_GOOD_CUP' => 'Very Good Cup',
            'GOOD_CUP_2R' => 'Good Cup 2R',
            'RIO_MINAS' => 'Rio Minas',
            'BICA_FINE_CUP' => 'Bica Fine Cup',
            'BICA_GOOD_CUP' => 'Bica Good Cup',
            'BICA_VERY_GOOD_CUP' => 'Bica Very Good Cup',
        ];
    }

    public function padraoLabel(): string
    {
        return self::padroes()[$this->padrao_final] ?? $this->padrao_final;
    }

    /**
     * Tipos de bebida (código => rótulo). Coluna VARCHAR, mesma ideia dos
     * padrões: acrescentar um tipo é só editar este array.
     *
     * @return array<string,string>
     */
    public static function tiposBebida(): array
    {
        return [
            'DURO' => 'Duro',
            'DURO_1RY' => 'Duro + 1RY',
            'DURO_2RY' => 'Duro + 2RY',
            'DURO_2RY_1RIO' => 'Duro + 2RY + 1 Rio',
            'DURO_2RY_2RIO' => 'Duro + 2RY + 2 Rio',
            'RIO' => 'Rio',
        ];
    }

    public function tipoBebidaLabel(): ?string
    {
        if ($this->tipo_bebida === null) {
            return null;
        }

        return self::tiposBebida()[$this->tipo_bebida] ?? $this->tipo_bebida;
    }
}
