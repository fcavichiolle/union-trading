<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fixacao extends Model
{
    // Sem isso o Eloquent procuraria a tabela "fixacaos" (pluralização inglesa).
    protected $table = 'fixacoes';

    protected $fillable = [
        'contrato_id', 'corretora', 'broker_cliente', 'tela', 'lotes', 'level', 'diferencial', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'lotes' => 'integer',
            'level' => 'decimal:2',
            'diferencial' => 'decimal:2',
            'preco' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Preço da tranche SEMPRE recalculado no servidor a partir de
        // level + diferencial — nunca aceito do formulário.
        static::saving(function (Fixacao $f) {
            $f->preco = round((float) $f->level + (float) $f->diferencial, 2);
        });
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Corretoras disponíveis na Tela NY (código => rótulo). Lista fixa por
     * decisão do projeto — para adicionar/renomear uma corretora, basta
     * editar este array (alimenta o formulário, a validação e a exibição).
     *
     * @return array<string,string>
     */
    public static function corretoras(): array
    {
        return [
            'STONEX' => 'StoneX East Coast',
            'ICAP' => 'ICAP Corporates LLC (Hedgepoint)',
            'MAREX_AGS' => 'Marex Financial Limited AGS Coffee',
        ];
    }

    /**
     * Brokers que o CLIENTE pode usar do lado dele (código => rótulo).
     * Campo opcional da fixação — mesma regra da lista acima: para
     * adicionar/renomear, basta editar este array.
     *
     * @return array<string,string>
     */
    public static function brokersCliente(): array
    {
        return [
            'STONEX_MIAMI' => 'Stonex Miami',
            'ADMIS' => 'Adm Investor Services Inc',
            'MACQUARIE_USA' => 'Macquarie USA',
            'STONEX_LONDON' => 'Stonex London',
            'SUCDEN_LONDON' => 'Sucden London',
            'MACQUARIE_FUTURES' => 'Macquarie futures broker LLC',
            'STONEX_EAST_COAST' => 'Stonex East Coast',
            'MAREX_LONDON' => 'Marex London',
        ];
    }

    public function corretoraLabel(): string
    {
        return self::corretoras()[$this->corretora] ?? $this->corretora;
    }

    public function brokerClienteLabel(): ?string
    {
        if ($this->broker_cliente === null) {
            return null;
        }

        return self::brokersCliente()[$this->broker_cliente] ?? $this->broker_cliente;
    }
}
