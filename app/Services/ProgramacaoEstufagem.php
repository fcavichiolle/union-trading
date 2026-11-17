<?php

namespace App\Services;

use App\Models\Embarque;
use App\Models\Transportadora;
use App\Models\User;
use App\Rules\ListaDeEmails;

/**
 * Programação de estufagem de um embarque: o e-mail que a logística manda
 * para o armazém e a transportadora, e que era montado à mão a partir da
 * planilha de embarques.
 *
 * O conteúdo completo (a tabela de datas e a tabela da fumigação) vai num
 * PDF — `mailto:` só leva texto puro, e o pessoal do armazém imprime a
 * programação. O e-mail em si sai com destinatários, assunto e um resumo
 * em texto; o PDF é anexado por quem envia.
 *
 * Destinatários: os e-mails do armazém de saída + os da transportadora
 * (achada pelo nome gravado no embarque). Cópia: a lista fixa da casa
 * (`config/exportacao.php`). Quem já é destinatário não repete na cópia.
 */
final class ProgramacaoEstufagem
{
    /** Mesmo separador das instruções de AA: o Outlook não separa por vírgula. */
    private const SEPARADOR = ';';

    /**
     * @param array<int, string> $para
     * @param array<int, string> $copia
     * @param array<int, string> $avisos
     */
    private function __construct(
        public readonly Embarque $embarque,
        public readonly array $para,
        public readonly array $copia,
        public readonly array $avisos,
        private readonly string $assinatura,
    ) {
    }

    public static function de(Embarque $embarque, ?User $remetente = null): self
    {
        $embarque->loadMissing(['contrato', 'armazem']);

        $transportadora = Transportadora::porNome($embarque->transportadora);

        $para = self::semRepetir(array_merge(
            $embarque->armazem?->emailsProgramacao() ?? [],
            $transportadora?->listaDeEmails() ?? [],
        ));
        $copia = self::semRepetir(ListaDeEmails::separar(config('exportacao.copia_fixa')), $para);

        $avisos = [];
        if (! $embarque->armazem) {
            $avisos[] = 'O embarque está sem armazém de saída.';
        } elseif ($embarque->armazem->emailsProgramacao() === []) {
            $avisos[] = "O armazém {$embarque->armazem->nome} não tem e-mails cadastrados (Cadastros → Armazéns).";
        }
        if (filled($embarque->transportadora) && ($transportadora?->listaDeEmails() ?? []) === []) {
            $avisos[] = "A transportadora \"{$embarque->transportadora}\" não tem e-mails cadastrados (Cadastros → Transportadoras).";
        }
        if (! $embarque->data_estufagem) {
            $avisos[] = 'O embarque ainda não tem data de estufagem.';
        }

        return new self($embarque, $para, $copia, $avisos, $remetente?->assinaturaEmail() ?? '');
    }

    /** "UT 0001 | CN 0001 TESTE-0001" */
    public function referencia(): string
    {
        $ref = $this->embarque->referencia();
        $buyer = $this->embarque->contrato->buyer_ref;

        return $buyer ? "{$ref} | {$buyer}" : $ref;
    }

    /**
     * Assunto no formato que a equipe já usa:
     * "PROGRAMAÇÃO ESTUFAGEM: UT 0001 | CN 0001 - BOOKING: 999TESTE0001 - MSC - ARMAZEM: SAAG"
     */
    public function assunto(): string
    {
        $partes = ['PROGRAMAÇÃO ESTUFAGEM: ' . $this->referencia()];

        if (filled($this->embarque->booking)) {
            $partes[] = 'BOOKING: ' . $this->embarque->booking;
        }
        if ($this->embarque->armazem) {
            $partes[] = 'ARMAZEM: ' . $this->embarque->armazem->nome;
        }

        return implode(' - ', $partes);
    }

    /** Resumo em texto puro — o detalhe completo é o PDF anexo. */
    public function corpo(): string
    {
        $e = $this->embarque;
        $d = fn ($data) => $data ? $data->format('d/m/Y') : '-';

        $linhas = [
            'Prezados,',
            '',
            'Segue em anexo a programação de embarque:',
            '',
            $this->referencia(),
            'Estufagem: ' . $d($e->data_estufagem),
            'Deadline de documentos: ' . $d($e->deadline_documentos),
            'Deadline de carga: ' . $d($e->deadline_carga),
            'Armazém: ' . ($e->armazem?->nome ?? '-'),
            'Transportadora: ' . ($e->transportadora ?: '-'),
            'Booking: ' . ($e->booking ?: '-'),
            'Quantidade: ' . number_format((float) $e->sacas, 0, ',', '.') . ' sacas ('
                . number_format((float) $e->quantidade_kg, 0, ',', '.') . ' kg)',
            '',
            'Gentileza revisar toda a documentação antes das 17h00.',
            '',
        ];

        if ($this->assinatura !== '') {
            $linhas[] = $this->assinatura;
        }

        return implode("\n", $linhas);
    }

    public function mailto(): string
    {
        $query = [];

        if ($this->copia !== []) {
            $query[] = 'cc=' . implode(self::SEPARADOR, $this->copia);
        }

        $query[] = 'subject=' . rawurlencode($this->assunto());
        $query[] = 'body=' . rawurlencode(str_replace("\n", "\r\n", $this->corpo()));

        return 'mailto:' . implode(self::SEPARADOR, $this->para) . '?' . implode('&', $query);
    }

    /** Sem destinatário não há e-mail para abrir (o PDF sai do mesmo jeito). */
    public function enviavel(): bool
    {
        return $this->para !== [];
    }

    /** "Programacao_UT_5942_19-11-2026.pdf" */
    public function nomeDoArquivo(): string
    {
        $ref = preg_replace('/[^A-Za-z0-9]+/', '_', $this->embarque->referencia());
        $data = ($this->embarque->data_estufagem ?? now())->format('d-m-Y');

        return "Programacao_{$ref}_{$data}.pdf";
    }

    /**
     * @param array<int, string> $emails
     * @param array<int, string> $jaEstaoEm
     * @return array<int, string>
     */
    private static function semRepetir(array $emails, array $jaEstaoEm = []): array
    {
        $vistos = array_map('mb_strtolower', $jaEstaoEm);
        $saida = [];

        foreach ($emails as $email) {
            $chave = mb_strtolower($email);
            if (in_array($chave, $vistos, true)) {
                continue;
            }
            $vistos[] = $chave;
            $saida[] = $email;
        }

        return $saida;
    }
}
