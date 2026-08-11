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
        'peneira_1718_pct',
        'peneira_1718_sacas',
        'peneira_1416_pct',
        'peneira_1416_sacas',
        'mercado_interno_pct',
        'mercado_interno_sacas',
        'grinders_pct',
        'grinders_sacas',
        'moka_pct',
        'moka_sacas',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'peneira_1718_pct' => 'decimal:2',
            'peneira_1718_sacas' => 'decimal:2',
            'peneira_1416_pct' => 'decimal:2',
            'peneira_1416_sacas' => 'decimal:2',
            'mercado_interno_pct' => 'decimal:2',
            'mercado_interno_sacas' => 'decimal:2',
            'grinders_pct' => 'decimal:2',
            'grinders_sacas' => 'decimal:2',
            'moka_pct' => 'decimal:2',
            'moka_sacas' => 'decimal:2',
            'quantidade_lotes' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        // Cálculo automático de lotes: SEMPRE recalculado no servidor a
        // partir da soma das sacas, nunca aceito como valor vindo do form.
        static::saving(function (Classificacao $classificacao) {
            $totalSacas = (float) $classificacao->peneira_1718_sacas
                + (float) $classificacao->peneira_1416_sacas
                + (float) $classificacao->mercado_interno_sacas
                + (float) $classificacao->grinders_sacas
                + (float) $classificacao->moka_sacas;

            $classificacao->quantidade_lotes = round($totalSacas / self::SACAS_POR_LOTE, 4);
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

    public function totalSacas(): float
    {
        return (float) $this->peneira_1718_sacas
            + (float) $this->peneira_1416_sacas
            + (float) $this->mercado_interno_sacas
            + (float) $this->grinders_sacas
            + (float) $this->moka_sacas;
    }

    /**
     * Padrões finais de classificação (código => rótulo). Lista central:
     * alimenta o dropdown, a validação e a exibição em todas as telas.
     * ATENÇÃO: ao adicionar um código aqui, adicione-o TAMBÉM ao ENUM da
     * coluna `padrao_final` (migration de ALTER), senão o MySQL trunca.
     *
     * @return array<string,string>
     */
    public static function padroes(): array
    {
        return [
            'FINE_CUP' => 'Fine Cup',
            'GOOD_CUP' => 'Good Cup',
            'GOOD_CUP_2R' => 'Good Cup 2R',
            'RIO_MINAS' => 'Rio Minas',
        ];
    }

    public function padraoLabel(): string
    {
        return self::padroes()[$this->padrao_final] ?? $this->padrao_final;
    }

    /**
     * Tipos de bebida (código => rótulo). Diferente de `padroes()`, a
     * coluna `tipo_bebida` é VARCHAR e não ENUM: para acrescentar um tipo
     * basta editar este array — não precisa de migration (ver a migration
     * ..._add_tipo_bebida_to_classificacoes.php).
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