<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Parcela de um contrato que sai num embarque (ver a migration para o
 * porquê). Substitui a aba VENDAS-FOB e a planilha de agenda das estufagens.
 */
class Embarque extends Model
{
    protected $table = 'embarques';

    protected $fillable = [
        'contrato_id', 'sufixo', 'mes_faturamento', 'quantidade_kg', 'status',
        'pss', 'pss_obs', 'armazem_id', 'transportadora',
        'data_fumigacao', 'data_estufagem', 'deadline_documentos', 'deadline_carga', 'data_fitossanitario',
        'destino', 'etd', 'navio', 'booking', 'pilha', 'faturado_em', 'observacoes', 'created_by',
        // Programação de estufagem (PDF + e-mail para armazém e transportadora).
        'ico', 'fumigacao_empresa', 'fumigacao_tipo', 'fumigacao_tempo', 'permit_import', 'instrucoes',
    ];

    protected function casts(): array
    {
        return [
            'mes_faturamento' => 'date',
            'quantidade_kg' => 'decimal:2',
            'sacas' => 'decimal:2',
            'data_fumigacao' => 'date',
            'data_estufagem' => 'date',
            'deadline_documentos' => 'date',
            'deadline_carga' => 'date',
            'data_fitossanitario' => 'date',
            'etd' => 'date',
            'faturado_em' => 'date',
        ];
    }

    /** Status que já tiraram o café do armazém (e a venda da posição). */
    public const STATUS_ENCERRADOS = ['FATURADO', 'EMBARCADO'];

    /**
     * Etapas do embarque, na ordem em que acontecem. Lista única: alimenta
     * formulário, validação, filtro e as cores da agenda.
     *
     * @return array<string, string> código => rótulo
     */
    public static function statuses(): array
    {
        return [
            'ABERTO' => 'A programar',
            'AG_BOOKING' => 'Aguardando booking',
            'BOOKING' => 'Booking confirmado',
            'AG_SI' => 'Aguardando SI',
            'PROGRAMADO' => 'Programação enviada',
            'FATURADO' => 'Faturado',
            'EMBARCADO' => 'Embarcado',
        ];
    }

    /** @return array<string, string> situação da amostra pré-embarque */
    public static function pssOpcoes(): array
    {
        return [
            'A_ENVIAR' => 'A enviar',
            'ENVIADA' => 'Enviada',
            'APROVADA' => 'Aprovada (OK)',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Embarque $e) {
            // Sacas SEMPRE calculadas no servidor, com o peso de saca do
            // contrato (59 kg na "Jute Bags 59kg", 60 no resto).
            $kgPorSaca = $e->contrato?->kgPorSaca() ?? Contrato::KG_POR_SACA;
            $e->sacas = round((float) $e->quantidade_kg / $kgPorSaca, 2);

            // O mês de faturamento é um MÊS: normaliza para o dia 01, senão
            // "2026-11-17" e "2026-11-01" virariam dois meses na posição.
            if ($e->mes_faturamento) {
                $e->mes_faturamento = Carbon::parse($e->mes_faturamento)->startOfMonth();
            }

            // A data da nota acompanha o status: sem ela não há como comparar
            // o faturamento com a data da foto do estoque. Voltar o status
            // para uma etapa aberta apaga a data (a nota foi desfeita).
            if ($e->encerrado()) {
                $e->faturado_em ??= now()->toDateString();
            } else {
                $e->faturado_em = null;
            }
        });
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function armazem(): BelongsTo
    {
        return $this->belongsTo(Armazem::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Já faturado/embarcado: o café saiu e a venda não pesa mais na posição. */
    public function encerrado(): bool
    {
        return in_array($this->status, self::STATUS_ENCERRADOS, true);
    }

    /** Embarques que ainda vão sair — o lado "vendido" da posição. */
    public function scopeEmAberto(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::STATUS_ENCERRADOS);
    }

    /** "UT 5973 B" — a referência como a mesa fala. */
    public function referencia(): string
    {
        return trim('UT ' . $this->contrato?->numero_ut . ' ' . $this->sufixo);
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->status] ?? $this->status;
    }

    /**
     * Dias até a estufagem (negativo = já passou). Nulo sem data ou quando
     * o embarque já foi faturado — aí o prazo não interessa mais.
     */
    public function diasAteEstufagem(): ?int
    {
        if (! $this->data_estufagem || $this->encerrado()) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->data_estufagem->copy()->startOfDay(), false);
    }
}
