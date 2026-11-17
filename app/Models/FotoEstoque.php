<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Foto do estoque físico (Digisul) numa data — a âncora do lado comprado
 * da posição Long & Short. Ver a migration para o porquê.
 *
 * A foto vale como ABERTURA do dia: entrega e baixa com a mesma data da
 * foto contam como posteriores a ela (a entrada no Digisul costuma ser
 * lançada depois da nota, então a nota do dia ainda não está na foto).
 */
class FotoEstoque extends Model
{
    protected $table = 'fotos_estoque';

    protected $fillable = ['data_foto', 'origem', 'observacao', 'created_by'];

    protected function casts(): array
    {
        return ['data_foto' => 'date'];
    }

    /** Baldes que são café ainda sem preparo: entram pela regra de rendimento. */
    public const SEM_PREPARO = ['BICA_FC', 'BICA_GC', 'REAPROV'];

    public const MERCADO_INTERNO = 'MERCADO_INTERNO';

    public function lotes(): HasMany
    {
        return $this->hasMany(FotoEstoqueLote::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** A foto que vale: a mais recente (e, no empate, a última lançada). */
    public static function atual(): ?self
    {
        return self::orderByDesc('data_foto')->orderByDesc('id')->first();
    }

    /**
     * Baldes da posição (código => rótulo): os standards de venda, o
     * mercado interno e o café ainda sem preparo.
     *
     * @return array<string, string>
     */
    public static function baldes(): array
    {
        return Contrato::standards() + [
            self::MERCADO_INTERNO => 'Mercado interno',
            'BICA_FC' => 'Bica Fine Cup',
            'BICA_GC' => 'Bica Good Cup',
            'REAPROV' => 'Reaproveitamento',
        ];
    }

    /**
     * De/para do padrão do Digisul para o balde da posição — é a coluna
     * que a mesa preenchia à mão na aba GERAL ESTOQUE. Padrão desconhecido
     * devolve null: o lote fica "sem balde" e a tela avisa, em vez de o
     * café sumir ou cair no balde errado.
     */
    public static function baldeDoPadrao(?string $padrao): ?string
    {
        $p = trim(preg_replace('/\s+/', ' ', Str::upper(Str::ascii((string) $padrao))));

        return match ($p) {
            '17/18 FC' => '1718_FC',
            '17/18 GC', 'PEN19 GC' => '1718_GC',
            '14/16 FC', '16 UP' => '1416_FC',
            '14/16 GC' => '1416_GC',
            'GRINDERS', 'GRINDERS 12 UP', 'GRINDERS_12UP' => 'GRINDERS_12UP',
            'GRINDERS 13 UP', 'GRINDERS_13UP' => 'GRINDERS_13UP',
            'MERCADO INTERNO', 'M.INTERNO', 'BLACK BEANS' => self::MERCADO_INTERNO,
            'BICA GOOD CUP', 'BICA GC' => 'BICA_GC',
            'BICA FINE CUP', 'BICA FC' => 'BICA_FC',
            'REAPROVEITAMENTO', 'REAPROV' => 'REAPROV',
            default => null,
        };
    }
}
