<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Armazém onde o café entra. Deixou de ser lista fixa no código (ENUM com
 * três valores) e virou cadastro, porque a mesa passou a usar outros.
 *
 * O CNPJ é opcional pelo mesmo motivo do fornecedor: o dado chega depois e
 * não vale travar o cadastro do armazém por causa dele.
 */
class Armazem extends Model
{
    // Sem isto o Laravel procuraria a tabela "armazems" (GOTCHA 2 do
    // PROGRESSO: a pluralização é feita em inglês).
    protected $table = 'armazens';

    protected $fillable = ['nome', 'cidade', 'estado', 'endereco', 'documento'];

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class);
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }

    /** Chave da memória por requisição. */
    private const MEMORIA = 'armazens.lista';

    /**
     * id => nome, para dropdowns, validação e exibição. A lista é pequena e
     * aparece várias vezes na mesma tela (formulário de compra, uma linha
     * por entrega, filtros de Estoque), então vale memorizar.
     *
     * Memorizada no CONTAINER, não num `static`: variável estática sobrevive
     * ao processo inteiro — nos testes, o banco é recriado a cada caso e a
     * lista voltaria com ids de outro banco. O container é recriado por
     * requisição (e por teste), então a memória morre junto.
     *
     * @return array<int,string>
     */
    public static function lista(): array
    {
        if (! app()->bound(self::MEMORIA)) {
            app()->instance(self::MEMORIA, self::orderBy('nome')->pluck('nome', 'id')->all());
        }

        return app(self::MEMORIA);
    }

    /** Esquece a memória — usada depois de cadastrar/editar/excluir. */
    public static function esquecerLista(): void
    {
        app()->forgetInstance(self::MEMORIA);
    }

    protected static function booted(): void
    {
        // Cadastro novo (ou renomeado) aparece na mesma requisição.
        static::saved(fn () => self::esquecerLista());
        static::deleted(fn () => self::esquecerLista());
    }

    /** Nome de um armazém pelo id, sem estourar quando o id não existe. */
    public static function nomeDe(?int $id): ?string
    {
        return $id === null ? null : (self::lista()[$id] ?? null);
    }

    /** "Santos/SP" — usado na lista do cadastro. */
    public function local(): string
    {
        return trim($this->cidade . '/' . $this->estado, '/');
    }

    public function documentoFormatado(): ?string
    {
        return Fornecedor::formatarDocumento($this->documento);
    }

    /** Estados brasileiros, para o dropdown do cadastro. */
    public static function estados(): array
    {
        return [
            'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG',
            'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
        ];
    }
}
