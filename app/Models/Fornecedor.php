<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vendedor do café. O documento (CNPJ ou CPF) é OPCIONAL: a mesa fecha
 * negócio com o vendedor "a confirmar" e o dado chega depois — exigir o
 * documento na hora do lançamento empurraria a equipe de volta para a
 * planilha. Sem documento, o fornecedor fica pendente (vira aviso no
 * painel inicial).
 */
class Fornecedor extends Model
{
    use HasFactory;

    protected $table = 'fornecedores';

    protected $fillable = ['nome', 'documento', 'tipo_documento'];

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }

    public function scopeSemDocumento(Builder $query): Builder
    {
        return $query->whereNull('documento');
    }

    /** Só os dígitos, do jeito que fica gravado. */
    public static function apenasDigitos(?string $documento): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $documento);

        return $digitos === '' ? null : $digitos;
    }

    /** CPF (11 dígitos) ou CNPJ (14) — null quando não dá para saber. */
    public static function tipoDoDocumento(?string $documento): ?string
    {
        return match (strlen((string) self::apenasDigitos($documento))) {
            11 => 'CPF',
            14 => 'CNPJ',
            default => null,
        };
    }

    /**
     * Reaproveita o fornecedor em vez de duplicar. Com documento, casa pelo
     * documento (é o identificador de verdade) e aproveita para atualizar o
     * nome. Sem documento, casa pelo nome — dois vendedores pendentes com o
     * mesmo nome digitado viram o mesmo cadastro, o que é aceitável até
     * alguém informar o documento.
     */
    public static function localizarOuCriar(string $nome, ?string $documento): self
    {
        $digitos = self::apenasDigitos($documento);

        if ($digitos !== null) {
            $fornecedor = self::firstOrNew(['documento' => $digitos]);
            $fornecedor->nome = $nome;
            $fornecedor->tipo_documento = self::tipoDoDocumento($digitos);
            $fornecedor->save();

            return $fornecedor;
        }

        return self::firstOrCreate(['nome' => $nome, 'documento' => null]);
    }

    /** "12.345.678/0001-99" ou "123.456.789-01"; null quando pendente. */
    public function documentoFormatado(): ?string
    {
        $d = $this->documento;

        if ($d === null) {
            return null;
        }

        if (strlen($d) === 14) {
            return vsprintf('%s%s.%s%s%s.%s%s%s/%s%s%s%s-%s%s', str_split($d));
        }

        if (strlen($d) === 11) {
            return vsprintf('%s%s%s.%s%s%s.%s%s%s-%s%s', str_split($d));
        }

        return $d;
    }
}
