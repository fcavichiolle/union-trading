<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Corretora;
use App\Models\Fixacao;
use App\Models\User;
use App\Rules\ListaDeEmails;
use Illuminate\Support\Collection;

/**
 * Instrução de AA ("against actuals") enviada ao broker depois de fixar.
 *
 * Substitui a planilha `AA instructions v2.xlsm`, cuja macro montava a
 * mensagem e abria o Outlook com `.Display` — ou seja, deixava pronto para
 * a pessoa conferir e enviar, sem enviar nada sozinha. Aqui o equivalente é
 * um link `mailto:`: o Outlook abre preenchido e o envio continua sendo um
 * clique humano, a partir da caixa de quem fixou (as respostas do broker
 * voltam para ela, não para uma caixa do sistema).
 *
 * Diferença de fundo para a planilha: lá os contratos eram digitados linha
 * a linha. Aqui a fixação já sabe quais contratos entraram no mesmo negócio,
 * e como o e-mail é sempre "Union X um cliente", as tranches de um grupo são
 * separadas POR CLIENTE — fixar 2 contratos de clientes diferentes de uma
 * vez gera duas instruções, cada uma com os seus contratos.
 *
 * O PREÇO da instrução é o `level`, não o `preco` da tranche: o AA se faz
 * contra a bolsa, e o diferencial é o prêmio do físico.
 */
final class InstrucaoAA
{
    /** Quem vende, como aparece no corpo do e-mail (nome de pregão). */
    public const REMETENTE = 'UNION TRADING';

    /**
     * Teto prático da URL `mailto:` que o Outlook aceita — acima disso a
     * mensagem chega cortada. Não é limite do sistema: é do Windows/Outlook,
     * por isso o aviso na tela em vez de um erro.
     */
    public const LIMITE_MAILTO = 2000;

    /**
     * @param array<int, array{ut: string, ref: string}> $contratos
     * @param array<int, string> $para
     * @param array<int, string> $copia
     * @param array<int, string> $avisos
     */
    private function __construct(
        public readonly string $cliente,
        public readonly string $corretora,
        public readonly ?string $brokerCliente,
        public readonly int $lotes,
        public readonly ?string $commodity,
        public readonly float $price,
        public readonly array $contratos,
        public readonly array $para,
        public readonly array $copia,
        public readonly array $avisos,
        private readonly string $assinatura,
    ) {
    }

    /**
     * Monta uma instrução por cliente a partir das tranches informadas
     * (normalmente as de um mesmo `grupo` de fixação).
     *
     * @param Collection<int, Fixacao> $fixacoes
     * @return array<int, self>
     */
    public static function deFixacoes(Collection $fixacoes, ?User $remetente = null): array
    {
        $comContrato = $fixacoes->filter(fn (Fixacao $f) => $f->contrato !== null);

        if ($comContrato->isEmpty()) {
            return [];
        }

        // Agrupa pelo cliente do contrato. Usa o id quando existe e cai no
        // nome do snapshot quando o cadastro foi removido — senão todos os
        // contratos órfãos cairiam no mesmo balde.
        $porCliente = $comContrato->groupBy(
            fn (Fixacao $f) => $f->contrato->cliente_id ?: 'nome:' . $f->contrato->cliente_nome
        );

        return $porCliente
            ->map(fn (Collection $doCliente) => self::umaInstrucao($doCliente, $remetente))
            ->values()
            ->all();
    }

    /** Atalho para uma tranche isolada (fixação antiga, sem grupo gravado). */
    public static function deFixacao(Fixacao $fixacao, ?User $remetente = null): ?self
    {
        return self::deFixacoes(collect([$fixacao]), $remetente)[0] ?? null;
    }

    /** @param Collection<int, Fixacao> $doCliente */
    private static function umaInstrucao(Collection $doCliente, ?User $remetente): self
    {
        /** @var Fixacao $primeira */
        $primeira = $doCliente->first();
        $contrato = $primeira->contrato;

        $corretora = Corretora::porNome($primeira->corretora, 'NOSSA');
        $cliente = $contrato->cliente; // pode ser null: cadastro removido

        $para = self::semRepetir($corretora?->emailsPara() ?? []);

        // Cópia = a da corretora + a do cliente + a cópia fixa da casa
        // (config/aa.php). Quem já está no "Para" não se repete aqui, senão
        // a pessoa receberia a mesma instrução duas vezes.
        $copia = self::semRepetir(array_merge(
            $corretora?->emailsCopia() ?? [],
            $cliente?->emailsCopia() ?? [],
            ListaDeEmails::separar(config('aa.copia_fixa')),
        ), jaEstaoEm: $para);

        $avisos = [];
        if ($para === []) {
            $avisos[] = "A corretora \"{$primeira->corretora}\" não tem destinatários cadastrados — "
                . 'preencha "E-mails (Para)" em Cadastros → Corretoras.';
        }
        if ($primeira->tela === null || $primeira->tela === '') {
            $avisos[] = 'Esta fixação não tem tela gravada, então o campo Commodity/Month sai em branco.';
        }

        $contratos = $doCliente
            ->sortBy(fn (Fixacao $f) => $f->contrato->numero_ut)
            ->map(fn (Fixacao $f) => [
                'ut' => 'UT ' . $f->contrato->numero_ut,
                'ref' => (string) ($f->contrato->buyer_ref ?? ''),
            ])
            ->values()
            ->all();

        return new self(
            cliente: $contrato->cliente_nome,
            corretora: $primeira->corretora,
            brokerCliente: $primeira->broker_cliente,
            lotes: (int) $doCliente->sum('lotes'),
            commodity: Contrato::commodityDaTela($primeira->tela),
            price: (float) $primeira->level,
            contratos: $contratos,
            para: $para,
            copia: $copia,
            avisos: $avisos,
            assinatura: $remetente?->assinaturaEmail() ?? '',
        );
    }

    /**
     * Tira repetidos ignorando maiúsculas/minúsculas (na prática e-mail não
     * diferencia) e descarta quem já está em `$jaEstaoEm`. Preserva a grafia
     * da primeira aparição — o endereço vai para a tela como foi cadastrado.
     *
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

    public function assunto(): string
    {
        return 'AA ' . self::REMETENTE . ' X ' . $this->cliente;
    }

    /**
     * Corpo em texto puro, no mesmo formato que os brokers já recebem hoje
     * da planilha — inclusive os números no padrão brasileiro (271,25), que
     * é o que o `Format` do VBA produz nas máquinas da mesa.
     */
    public function corpo(): string
    {
        $linhas = [
            'Prezados,',
            '',
            'segue instrução para o AA:',
            '',
            'FROM: ' . self::REMETENTE,
            'TO: ' . $this->cliente,
            'Broker: ' . ($this->brokerCliente ?: '-'),
            'Lots: ' . $this->lotes,
            'Commodity/Month: ' . ($this->commodity ?? ''),
            'Price: ' . number_format($this->price, 2, ',', '.'),
            'Contract(s):',
        ];

        foreach ($this->contratos as $c) {
            $linhas[] = $c['ref'] === '' ? $c['ut'] : $c['ut'] . ' / ' . $c['ref'];
        }

        $linhas[] = '';

        if ($this->assinatura !== '') {
            $linhas[] = $this->assinatura;
        }

        return implode("\n", $linhas);
    }

    /**
     * Separador de destinatários: **ponto e vírgula**, não vírgula.
     *
     * A RFC 6068 manda separar por vírgula, mas o Outlook do Windows só
     * aceita vírgula quando a opção "é possível usar vírgulas para separar
     * vários destinatários" está ligada — e ela vem DESLIGADA. Com vírgula
     * ele lê a lista inteira como UM endereço só, e o e-mail não sai. Ponto
     * e vírgula ele sempre entende. Como este link existe para abrir o
     * Outlook, vale o que o Outlook entende, não o que a RFC prefere.
     *
     * A macro da planilha não tinha esse problema porque adicionava os
     * destinatários um a um (`Recipients.Add`), e não por URL.
     */
    private const SEPARADOR_DESTINATARIOS = ';';

    /**
     * Link que o Outlook abre já preenchido. Destinatários ficam crus;
     * assunto e corpo vão percent-encoded, com a quebra de linha como %0D%0A.
     */
    public function mailto(): string
    {
        $query = [];

        if ($this->copia !== []) {
            $query[] = 'cc=' . implode(self::SEPARADOR_DESTINATARIOS, $this->copia);
        }

        $query[] = 'subject=' . rawurlencode($this->assunto());
        $query[] = 'body=' . rawurlencode(str_replace("\n", "\r\n", $this->corpo()));

        return 'mailto:' . implode(self::SEPARADOR_DESTINATARIOS, $this->para)
            . '?' . implode('&', $query);
    }

    /** O `mailto:` passou do que o Outlook aceita sem cortar? */
    public function linkLongoDemais(): bool
    {
        return strlen($this->mailto()) > self::LIMITE_MAILTO;
    }

    /** Pode abrir o Outlook? Sem destinatário, não há o que enviar. */
    public function enviavel(): bool
    {
        return $this->para !== [];
    }

    /** @return array<int, string> avisos de tela, já incluindo o do tamanho */
    public function avisos(): array
    {
        $avisos = $this->avisos;

        if ($this->linkLongoDemais()) {
            $avisos[] = 'A mensagem ficou longa (' . strlen($this->mailto()) . ' caracteres) e o Outlook '
                . 'pode cortá-la ao abrir — confira o final do texto, ou use "Copiar texto" e cole na mensagem.';
        }

        return $avisos;
    }
}
