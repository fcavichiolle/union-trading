# Union Trading — Estado do Projeto (PROGRESSO)

> Documento de contexto para retomar o desenvolvimento (inclusive no Claude Code).
> O código em si mora nos arquivos do projeto; este resumo guarda o "porquê" das
> decisões, os problemas já resolvidos e o que ainda falta.
>
> Última atualização: novembro/2026.

---

## 1. O que é o sistema

Sistema web **B2B interno** da Union Trading para registrar **compras de café** e a
**classificação** dessas compras (distribuição em peneiras), com um relatório
gerencial somente leitura. Uso interno por equipes de compras, financeiro e diretoria
— **não tem cadastro público**.

## 2. Stack e ambiente local

- **PHP 8.3** (via Laragon) + **Laravel 13** + **MySQL 8** (banco `union_trading`).
- Sem pacotes de terceiros para auth/permissões — tudo escrito à mão (mais fácil de auditar).
- CSS num único arquivo de views, **sem build step** (não usa Node/Vite/Tailwind compilado).
- **Mailpit** disponível no Laragon para testar e-mails (hoje `MAIL_MAILER=log`, então
  e-mails vão para `storage/logs/laravel.log`; trocar para `smtp` + porta `1025` para usar o Mailpit).

### Como rodar
1. Ligar o **Laragon** (sobe Apache/MySQL).
2. No terminal, dentro de `C:\laragon\www\union-trading`:
   ```
   php artisan serve
   ```
3. Acessar `http://localhost:8000/login` (não existe página na raiz `/`, é de propósito).

### Primeiro acesso / recriar do zero
- O primeiro admin é criado por seeder, usando `ADMIN_EMAIL` do `.env`
  (senha temporária aleatória aparece no terminal; troca obrigatória no 1º login).
- Recriar tudo do zero (ambiente de teste, **apaga dados**):
  ```
  php artisan migrate:fresh --seed
  ```
- O e-mail do admin é o definido em `ADMIN_EMAIL` no `.env` (não fica aqui: o repositório é público).

## 3. O que já está implementado

### Módulo 0 — Segurança e controle de acesso
- Login com proteção contra força bruta e sessão renovada no login.
- "Esqueci minha senha" (link por e-mail) e "Alterar senha".
- Troca de senha obrigatória no primeiro acesso (campo `force_password_change`).
- Perfis (`roles`): **admin, compras, financeiro, diretoria**; cada rota protegida por
  `middleware('role:...')` no backend.
- Admin cria os usuários da equipe (sem cadastro público em nenhuma rota).
- **Tela de login redesenhada** (visual "café"): fundo em gradiente verde→marrom, grãos de
  café caindo (SVG animado) e vapor desfocado, com **card de vidro fosco** centralizado.
  `resources/views/auth/login.blade.php` deixou de usar `layouts.guest` e virou **página
  completa e autossuficiente** (as outras telas de auth — esqueci/redefinir/trocar senha —
  continuam no `layouts.guest`). O CSS mora em `partials/styles.blade.php` (bloco "Tela de
  LOGIN — cena café"). Escolhas p/ ficar **leve em qualquer aparelho**: sem fontes externas
  (usa a stack de sistema e `--font-data` para os rótulos mono), **sem imagem de logo** (marca
  em texto na plaquinha creme), fallback sólido via `@supports` quando não há `backdrop-filter`,
  (grãos de café caindo + vapor). **Por decisão do projeto, os grãos/vapor caem sempre**,
  mesmo com "reduzir movimento" ligado no sistema (a animação é leve — SVG + `transform` na
  GPU — e é a identidade da tela); há um comentário em `styles.blade.php` explicando como
  voltar a respeitar `prefers-reduced-motion` se quiser. Os grãos usam **delay negativo** para
  já aparecerem preenchidos no carregamento. O formulário segue idêntico (POST `route('login')`,
  `@csrf`, campos email/password/remember, estados de erro).
- **Logo Union na tela de login**: usa o **logo oficial** em `public/img/union-trading.png`
  (fundo removido → PNG transparente, gerado com Pillow a partir do JPEG original que ficou em
  `public/`). O partial `resources/views/partials/logo-union.blade.php` só renderiza o `<img>`.
  Fica na plaquinha creme do card porque o logo é verde-escuro (some em fundo escuro). Para
  regenerar o recorte, o script está em scratchpad (`rmbg.py`): abre o JPEG, torna transparente
  o que é quase-branco (`min(r,g,b) >= 245`, com feathering) e recorta as margens.
- **Shell redesenhado (sidebar verde + header)**: o `layouts/app.blade.php` foi refeito para o
  visual do design — **sidebar verde** (272px) com o logo na plaquinha creme, navegação agrupada
  (Início / Compras & Classificação / Administração) com estado ativo iluminado + barra vermelha,
  rodapé "Sistema operacional"; **header** com breadcrumb (`@yield('crumb')`) + avatar (iniciais)
  + nome/perfil + "Trocar senha"/"Sair"; e um **page-head** que renderiza `@yield('title')` como
  `<h1>` grande, com `@section('subtitle')` e `@section('page_actions')` opcionais. Todas as telas
  autenticadas (compras, relatório, usuários) herdam esse shell automaticamente. CSS em
  `partials/styles.blade.php` (blocos "App shell", "Início", "Gestão de usuários").
- **Início — painel** (`dashboard/home.blade.php`): deixou de ser 4 cards de atalho (que só
  repetiam o menu lateral) e virou um painel que responde **"o que falta fazer agora?"**.
  Três faixas: (1) **pendências** em cards clicáveis que levam à tela já filtrada —
  compras sem nº do lote, não classificadas, sem financeiro, contratos a fixar e contratos
  sem mês de embarque; (2) **posição geral** — sacas compradas, sacas em contrato, saldo
  (compras − contratos) e lotes a fixar; (3) **últimos lançamentos** (compras e contratos).
  Saudação usa o nome do usuário logado.
  **Regras de desenho**: card com contador **zero não aparece** (se nada está pendente, sai
  o aviso verde "Tudo em dia"); cada pendência tem seus perfis (ex.: "sem financeiro" também
  para o perfil financeiro; diretoria vê só a posição). Os números são **totais gerais, sem
  recorte de período** — decisão do projeto para não competir com o Relatório e com os
  filtros de "Compras lançadas" (há uma nota na própria tela dizendo isso, e que o saldo
  não considera estoque anterior ao sistema).
- **Badges de pendência no menu lateral**: "Compras lançadas" mostra quantas compras têm
  alguma etapa em aberto e "Tela NY" quantos lotes estão a fixar. Só aparecem quando > 0
  (badge em tudo vira enfeite e para de ser lido). Alimentados por um **View composer** de
  `layouts.app` no `AppServiceProvider`.
- **Serviço `App\Services\PainelInicial`**: concentra as contagens/pendências/badges e é
  registrado como **singleton** — o layout (badges) e a home pedem os mesmos números na
  mesma requisição e as queries rodam uma vez só. Scopes novos no model `Compra`:
  `semNumeroLote()` (versão SQL de `precisaDeNumeroLote()`) e `comPendencia()`.
  "Compras lançadas" ganhou o filtro **Pendência** (`?pendencia=sem_lote|sem_classificacao|
  sem_financeiro|qualquer`), que é o destino dos cards do painel.
- **Gestão de usuários** (`admin/users/index.blade.php`): igual ao design — banner vermelho
  "Cadastro público desativado", **tabela de usuários** (avatar com iniciais, perfil em pill,
  último acesso, status Ativo/Suspenso; ações Editar/Resetar senha aparecem no hover e ficam
  fixas no mobile) e **formulário lateral "Adicionar usuário interno"** (nome, e-mail, perfil)
  que posta no `admin.users.store` já existente. A caixa "permissões do perfil" mostra a
  `descricao` real do Role selecionado via um JS mínimo (não inventa permissões). O
  `UserController::index` agora também passa `$roles` para a view.

### Módulo 1 — Compras e Classificação

> **COMPRA ≠ ENTREGA (mudança estrutural, ago/2026).** Antes uma compra era
> uma linha só (um armazém, um volume, um nº de lote) e `uts` é única — era
> impossível registrar a realidade da mesa: *a mesma UTS entra em partes, em
> meses e armazéns diferentes, cada parte com o seu lote*. Agora:
> - **`compras`** = o NEGÓCIO fechado (funcionário 1 → funcionária 2): UTS,
>   data, vendedor, **volume contratado**, preço, corretor, comissão,
>   pagamento (data + observação livre), logística (POSTO/RETIRAR) e certificação;
> - **`entregas`** = cada ENTRADA física (funcionário 3): mês, armazém,
>   **volume real** e número do lote.
>
> O volume da entrega **não é limitado pelo contratado** — pode vir mais ou
> menos, quem confere é o armazém; o sistema mostra a diferença em vez de
> impedir. Daí saem `Compra::sacasEntregues()`, `saldoAEntregar()`,
> `entregouAMais()`, `valorContratado()` e `valorEntregue()` (paga-se pelo
> que entrou). A tabela `financeiro_compras` foi **removida**: preço,
> corretor e comissão são dados da negociação e vivem na compra — a tela do
> perfil financeiro continua existindo, editando essas colunas.
> **ARÁBICA × CONILON, PESO E DATA DA ENTREGA (ago/2026).** Três mudanças
> pedidas pela mesa, todas com efeito em cadeia:
> - **`compras.tipo_entrada`** deixou de ser texto livre com "BICA" (que não
>   era espécie de nada) e virou **ARABICA** (pré-selecionado) ou **CONILON**,
>   em dropdown. `Compra::tiposEntrada()`.
> - **Padrão final e tipo de bebida** passaram a ser informados **já no
>   lançamento da compra** — são parte do negócio fechado. Ficam em
>   `compras.padrao_final` / `compras.tipo_bebida` (nullable). Quando o café é
>   **conilon, os dois campos desaparecem da tela** (JS) e são gravados como
>   nulos no servidor, inclusive na classificação e inclusive quando a compra
>   muda de espécie depois de classificada. A classificação continua sendo a
>   dona das **peneiras**, e ao salvar ela a compra acompanha o padrão
>   corrigido (uma verdade só, não duas telas divergindo).
> - **PESO ao lado das sacas**, na compra e na entrega: preencher um calcula o
>   outro a **60 kg/saca** (`Compra::KG_POR_SACA`, `pesoDeSacas()`,
>   `sacasDePeso()`, `completarSacasEPeso()`), no navegador **e** no servidor
>   (`prepareForValidation`), porque o armazém às vezes informa quilos e às
>   vezes sacas. Os dois valores são gravados **como informados**: 200 sacas
>   pesando 12.010 kg é realidade de armazém, e "corrigir" apagaria informação.
> - **A entrega guarda o DIA** (`entregas.data_entrega`, antes `mes_ano`, que
>   era normalizado para o dia 01): a auditoria precisa saber quando o café
>   entrou. Por isso o filtro de mês do Estoque passou a fechar o **fim do
>   mês** no limite de cima — com `mes_ate . '-01'` tudo que entrasse depois
>   do dia 1º ficava fora do recorte.
- **Cadastro de compra**: UTS, data, fornecedor + CNPJ/CPF opcional, certificação,
  **tipo de café (arábica/conilon)**, **padrão final + tipo de bebida** (só arábica),
  volume em **sacas e/ou peso**, logística, preço e pagamento.
- **Número do lote** (`compras.numero_lote`, coluna nova): preenchido **depois** do lançamento
  (não faz parte do formulário de criação — é adicionado quando o armazém/controle de estoque
  informa o número). Enquanto estiver em branco (`Compra::precisaDeNumeroLote()`), a compra
  **não é considerada definitivamente em estoque**: aparece um badge de alerta vermelho
  "⚠ Falta nº do lote" na coluna "Lote" de "Compras lançadas" e um banner de alerta no topo da
  tela da compra, com um formulário simples (`compras/{compra}/lote`, `PUT`) para preenchê-lo.
  Rota/controller (`CompraController::atualizarLote`) restrita a admin/compras, igual ao resto
  do módulo.
- **Tipo de bebida** na classificação (`classificacoes.tipo_bebida`): Duro, Duro + 1RY,
  Duro + 2RY, Duro + 2RY + 1 Rio, Duro + 2RY + 2 Rio e Rio. Lista em
  `Classificacao::tiposBebida()`. **A coluna é VARCHAR, não ENUM, de propósito**: assim
  incluir um tipo novo é só editar o array — sem a migration de ALTER que `padrao_final` e
  `certificacao` exigem (GOTCHA 3). Nullable no banco (classificações antigas não têm o
  dado), obrigatório no formulário. Aparece na tela da compra e sob o padrão em
  "Compras lançadas".
- **Faixas de peneira em UMA lista** (`Classificacao::faixas()`, ago/2026): o PROGRESSO
  avisava que faixa nova precisava ser somada **em 4 lugares** (model, request, SQL do
  estoque e tabelas de exibição) — e esquecer um deles dava número errado calado. Agora
  todos leem a mesma lista: model (`getFillable`, casts, `totalSacas`, `totalPct`),
  `StoreClassificacaoRequest`, `DashboardController::distribuicao()` (uma coluna somada
  por faixa, com o **prefixo como apelido** — a view lê `$linha->peneira_1718`) e as
  tabelas (`compras/show`, `dashboard/_tabela-estoque`, `_tabela-classificacao`). Faixa
  nova = uma linha em `faixas()`. **Acrescentadas SCS 12 UP e SCS 13 UP**, acima da 17/18.
- **Padrões novos**: Very Good Cup, Bica Fine Cup, Bica Good Cup e Bica Very Good Cup.
  Foi possível porque `classificacoes.padrao_final` **deixou de ser ENUM e virou VARCHAR
  NULL** (migration `..._padroes_novos_e_peneiras_12up_13up`): com ENUM, cada padrão novo
  exigia migration de `ALTER` que só rodava no MySQL e o SQLite dos testes **recusava** o
  código novo — era impossível testar um padrão recém-criado (o antigo GOTCHA 3). A lista
  agora vive só em `Classificacao::padroes()` e alimenta dropdowns, validação e filtros.
- **Classificação**: padrão final + tipo de bebida (herdados da compra, corrigíveis aqui),
  distribuição nas peneiras 12 UP, 13 UP, 17/18, 14/16, mercado interno, grinders e
  **moka** (% e sacas). O erro de soma (100% e teto de sacas) tem **chave própria**
  (`soma_pct`/`soma_sacas`) e aparece acima da tabela — antes era pendurado na primeira
  peneira, o que virava mensagem no campo errado. **Cálculo de lotes** (total de sacas ÷ 283,49) feito
  sempre no servidor. A linha "Moka" foi adicionada depois das demais (colunas `moka_pct`/
  `moka_sacas` em `classificacoes`, via migration de `ALTER` — mesma lógica do ENUM de
  certificação: nunca editar a migration de criação já rodada). A view do formulário
  (`compras/classificacao.blade.php`) é **data-driven** (array de `[campo, rótulo]`), então
  novas faixas de peneira bastam entrar nesse array, sem tocar no JS de cálculo automático.
  Toda faixa nova precisa ser somada em 4 lugares: `Classificacao::booted()` (lotes),
  `StoreClassificacaoRequest` (soma % e soma sacas), `DashboardController::distribuicao()`
  (SQL do relatório) e as tabelas que exibem a distribuição (`compras/show`,
  `dashboard/_tabela-classificacao`).
- **Financeiro**: valor da saca, valor total (= saca × volume, calculado no servidor),
  corretor e comissão. Tem **preview do total em tempo real** no formulário (só visual;
  o valor oficial continua vindo do servidor ao salvar).
- **Liquidação da compra** (`compras.liquidar` / `compras.reabrir`, PATCH): o armazém quase
  nunca recebe exatamente o contratado — vêm 260 no lugar de 250, ou 240 e ficou por isso
  mesmo. Enquanto ninguém decide, o sistema **avisa da diferença**, porque pode haver café a
  receber de verdade. Ao **liquidar**, o volume entregue vira o final: os avisos somem da
  tela da compra e de "Compras lançadas", e a UTS sai da pendência "saldo a entregar".
  **`volume_contratado` NÃO é sobrescrito** de propósito — a diferença (quebra ou excedente)
  é informação real e continua visível; a liquidação é uma decisão registrada com data e
  autor (`liquidada_em`/`liquidada_por` + AuditLog), não um apagamento. **Reabrir** desfaz.
  Só é possível liquidar UTS com ao menos uma entrega. Filtro novo em "Compras lançadas":
  **Divergência a liquidar**. Ver `Compra::divergenciaPendente()` e `volumeReconhecido()`.
  Dois acertos de acabamento (11/ago): o número da diferença já vinha marcado com
  `.is-alerta` na tela da compra, mas só existia regra CSS para `.num-tile__val` —
  o destaque âmbar **nunca aparecia** (corrigido em `styles.blade.php`, claro e
  escuro); e o aviso de liquidada saía com espaço antes do ponto
  ("(contratado era 600,00 sc) .", ver GOTCHA 8).
- **Fornecedor com CNPJ ou CPF, e opcional**: a coluna virou `documento` (só dígitos) +
  `tipo_documento`. Pode ficar em branco ("vendedor a confirmar", como na planilha da mesa)
  e vira pendência no painel — exigir o documento empurrava a funcionária 2 de volta para o
  Excel. Validação de CNPJ **e** CPF em `App\Rules\DocumentoValido`. **Busca automática do
  nome por CNPJ** via BrasilAPI (`App\Services\ConsultaCnpj`, pública e sem chave, cache de
  30 dias) — é conveniência, nunca dependência: se a API cair, o nome é digitado.
  **Não existe consulta pública de nome por CPF no Brasil** (dado pessoal protegido): para
  CPF o sistema só valida os dígitos. Reaproveitamento do cadastro em
  `Fornecedor::localizarOuCriar()` — casa pelo documento quando há, pelo nome quando não há.
- **Rateio da classificação no Estoque**: a classificação é da **UTS inteira** (decisão do
  projeto), mas o café pode ter entrado em vários armazéns. O Estoque distribui cada peneira
  entre as entregas na proporção `volume da entrega ÷ total classificado`, então o total do
  estoque bate com o que realmente entrou. **Cuidado**: o `* 1.0` no SQL é obrigatório — no
  SQLite a divisão entre dois inteiros trunca (250/500 = 0) e o rateio zeraria em silêncio.
- **Estoque** (antigo "Relatório de classificação", rota continua `relatorio.*`): a tela
  passou a ser o **estoque** da empresa. **Regra central: só entra em estoque
  definitivamente a compra que já tem o número do lote** informado pelo armazém — filtro
  **Situação** com `definitivo` (padrão), `aguardando` e `todos`. Ganhou **filtro e coluna
  de armazém**, com as linhas agrupadas por armazém (subtotal quando o armazém tem mais de
  um padrão), porque a pergunta de estoque é "o que tenho na SAAG?".
  **Nada some em silêncio**: dois avisos no topo mostram o que existe mas está fora da
  tabela — (1) volume **aguardando nº do lote** e (2) volume **com lote mas sem
  classificação** (é estoque real, só não tem distribuição de peneira lançada, então não
  tem linha na tabela). Ambos linkam para "Compras lançadas" já filtrado. Esses avisos
  respeitam mês/certificado/armazém/busca, mas **ignoram de propósito** os filtros
  `padrao` e `situacao` — são justamente os recortes que esconderiam esses volumes.
  **ATENÇÃO — é ENTRADA, não SALDO**: o sistema ainda não registra embarque/faturamento,
  então os totais dizem quanto entrou, não quanto resta para vender (há nota na tela).
  O fechamento real depende do módulo de embarque (ver "próximos passos").
- **Link compartilhável x armazém**: o armazém **não viaja** no link assinado
  (`FILTROS_LINK` não inclui `armazem`) e a versão pública nunca quebra por armazém, mesmo
  se o parâmetro for forçado na URL — decisão do projeto: onde o café está guardado é
  informação de casa, e um link com filtro de armazém invisível entregaria números
  parciais sem o destinatário saber. A tela pública diz qual recorte está mostrando
  ("Considera apenas o café com entrada confirmada em armazém"), sem citar armazém.
- **Relatório (dashboard)** somente leitura, com **link temporário assinado (7 dias)** para
  compartilhar sem login. Tem uma única tabela — **Distribuição por padrão × peneira**
  (a tabela de "Distribuição por certificação" foi **removida**: tinha o mesmo "Total geral"
  da tabela de padrão, então era informação duplicada na tela; "certificado" virou filtro).
  Filtros: **intervalo de meses** (mes_de/mes_ate), **padrão**, **certificado** e **busca**
  (UTS ou fornecedor) — mesmo padrão de filtro da tela "Compras lançadas". Os filtros ativos
  são **carregados no link compartilhável** (assim quem recebe o link vê a mesma visão
  filtrada). Lista de filtros centralizada em `DashboardController::FILTROS`.
- **Tela "Compras lançadas"** com filtros por **intervalo de meses**, por **padrão** e
  busca por UTS/fornecedor; colunas de resumo (Volume, Mercado interno, Grinders) e
  coluna de **Certificação**, para ver tudo sem abrir compra por compra.

#### Carga das compras e conferência com o Digisul (17/11/2026)
Primeiro passo para o estoque do sistema bater com o real. **Os números,
nomes e divergências de cada carga estão em
`storage/app/private/REGISTRO-DE-CARGAS.md` (fora do Git — o repositório é
público).** Aqui ficam só as regras.

- Scripts em `storage/app/private/`, sempre no mesmo molde: um extrator em
  Python gera um JSON e um `importar_*.php` simula por padrão e só grava com
  `APLICAR=1`, com backup antes. `extrair_compras.py` + `importar_compras.php`
  (planilha fiscal, uma linha por NOTA, e a aba COMPRA da posição);
  `importar_digisul_compras.php` (relatório de compras do Digisul);
  `separar_nf_de_lote.php`; `backup_banco.php` (mysqldump completo).
- **Alcance decidido pela mesa: o mês corrente + o que está a receber**, não
  o ano inteiro (quase tudo já foi vendido e encheria o painel de
  pendências). O estoque parte de um saldo/foto e anda com o que entra e
  sai depois — ver "Posição Long & Short".
- **Cada NOTA vira uma entrega.** UTS com sufixo na planilha (7435-A,
  7418-B) é UMA compra. Compra ainda sem nota usa o rótulo da posição
  (`CORRETOR x% | VENDEDOR`; sem a barra, o rótulo é o vendedor); vendedor
  desconhecido vira o fornecedor "A CONFIRMAR", sem documento — pendência
  até a nota chegar.
- **NF e lote são coisas diferentes** (mesa, 17/11: *"depois pegamos vários
  lotes para compor um contrato e de lá sai uma guia de serviço que vai para
  o armazém"*). A cadeia é: **nota fiscal → entrada no Digisul (nasce o
  LOTE) → vários lotes compõem um contrato de venda → GUIA DE SERVIÇO para
  o armazém preparar**. A guia (PI-…, RAP-… no estoque do Digisul) é o elo
  entre estoque e venda — ainda não existe no sistema.
  - `entregas.nota_fiscal` (migration `2026_04_01_000004`), com campo na
    tela da compra. `numero_lote` é só o lote do Digisul; **nota sem lote =
    entrada pendente no Digisul** — a fila "aguardando nº do lote" que já
    existia (pendência em Compras lançadas, "Fora do estoque" na tela
    Estoque). É a origem do retrabalho de conferência da mesa: a nota chega
    e a entrada é lançada depois.
  - **Erro da primeira carga, para não repetir**: gravar a NF no campo do
    lote marcou como estoque definitivo também o que ainda não tinha
    entrada. Corrigido no mesmo dia.
  - O relatório de compras do Digisul lista os lotes por CONTRATO, não por
    nota: nas UTS com vários lotes, cada nota mostra a lista inteira. E ele
    **só lista contrato já fechado** — UTS com entrada parcial não aparece
    nele (aparece no export de estoque).
- **Classificação trazida do Digisul** (padrão, bebida e peneiras) para as
  compras que existem nos dois lados. De/para das faixas: `17/18`→SCS 17/18,
  **`13/16`→SCS 14/16** (o Digisul chama de 13/16 o que a posição chama de
  14/16), `GRINDERS 12 UP`→SCS 12 UP, `CONSUMO`→Mercado interno. **É a
  peneira do contrato de compra (rendimento esperado, "classificação inicial
  baseada nas amostras de compra"), não a conferência do classificador** —
  ele pode corrigir na tela. Volume, lote e a data "Liquidado" do Digisul
  NÃO são importados (volume vem das notas; liquidar é ato do financeiro).
- **O estoque fecha no agregado, não lote a lote.** O estoque do Digisul é
  quase todo café JÁ PREPARADO: depois do rebenefício o lote da compra some,
  e só uma parte pequena ainda tem elo com uma UTS. A conta é saldo/foto +
  entradas − baixas, por padrão.

### Módulo 2 — Contratos de exportação
- **Cadastros** (admin): **Clientes** (nome + endereço multilinha + **ref. padrão** opcional do
  comprador) e **Qualidades** (descrição do café). Rotas `admin.clientes.*` / `admin.qualidades.*`.
  Se o cliente tem ref. padrão, o formulário de contrato preenche o "Ref. Comprador" ao selecioná-lo
  (ex.: cliente **GAMA CF LLC** → `CONTRACT NO. 26-003 DD. 17.02.2026`).
- **Novo contrato** (`contratos.create`, perfis admin/compras): formulário em 4 blocos (igual ao
  app-modelo) com **preview ao vivo** de sacas/lotes/containers. Container rotulado **TEUS (20')** /
  **FEUS (40')**.
- **Cálculos (servidor, `Contrato::saving`)**: sacas = kg÷**60** (÷**59** quando a embalagem é
  "Jute Bags 59kg"); lotes = round(sacas ÷ 283,49 arábica | ÷ 166,66 conilon) — arredondamento
  normal (1,51→2); containers = ceil(kg ÷ capacidade), capacidade **20'=22.000 / 40'=25.000 kg**;
  peso/container = kg÷containers. **Arábica/Conilon é escolhido por contrato.**
- **Posições de bolsa (telas) são CALCULADAS pela data** (12/ago/2026). Antes eram três
  anos escritos à mão (`['6','7','8']`), o que deixava posição vencida na tela (H6, K6 e
  N6 apareciam em agosto/2026) e obrigava a editar código todo janeiro. Agora
  `Contrato::mesesFixacaoSantos()/Vitoria()` geram do mês corrente até **3 anos à frente**,
  e a posição sai da lista quando o **mês de entrega** passa (dentro do próprio mês ela
  continua, porque ainda se negocia nela). O código usa o **último dígito do ano** (H7 =
  março/2027), como na bolsa.
  **Existem DUAS listas de propósito**: as `mesesFixacao*()` (em aberto) alimentam os
  formulários; as `mesesFixacao*Todas()` (janela larga, 4 anos para trás) alimentam a
  **validação** e o **rótulo** — sem elas, editar um contrato fixado numa posição vencida
  passaria a dar "mês inválido", e a Tela NY perderia o rótulo do histórico. Onde a
  posição vencida ainda precisa aparecer, ela entra na lista marcada **"— já vencida"**:
  no formulário do contrato que está fixado nela e na Tela NY para os contratos em aberto
  (senão não haveria como fixar contra ela). `Contrato::rotuloDaTela()` resolve o rótulo de
  qualquer código e `telaEhDeLondres()` diz a qual bolsa ele pertence (antes isso era um
  `array_key_exists` na lista de Londres, que classificaria posição vencida como Santos).
  Guardado por `tests/Feature/PosicoesDeBolsaTest.php`, que **congela o tempo** — teste de
  calendário sem tempo congelado passa hoje e falha no ano que vem, que é justamente o
  problema que estávamos resolvendo.
- **PRICE muda conforme o porto**: **Santos** → `... cents/pounds under N lot(s) <cod> NY ICE ...`
  (meses NY ICE tipo `Z6`); **Vitória** → `... USD/MT of ICE ROBUSTA CF LONDON, N lot(s) x <mês> ...`
  (meses de Londres tipo `Sep_2026`). O formulário troca as opções de mês e a unidade do diferencial
  conforme o porto (JS). **Incoterms: só FOB.**
- **Contrato FIXED**: checkbox "Contrato já fixado (FIXED)" no formulário — quando marcado, mostra
  os campos **Preço fixado** + **Unidade** (em vez de Diferencial/Mês de fixação) e o PRICE do
  contrato passa a mostrar o **valor absoluto** em vez da fórmula "to be fixed": ex. `353,40 cts/lb`
  ou `3.725,00 USD/MT`. **A unidade (cts/lb ou USD/MT) é escolha livre do usuário, independente do
  porto** — é um valor negociado, não a unidade "oficial" da bolsa (essa (`Contrato::unidadePreco()`)
  continua fixa por porto, mas só é usada na fórmula "a fixar"). O formulário sugere a unidade
  "de costume" do porto ao trocar o porto, mas **só enquanto o usuário não tiver escolhido
  manualmente** (rastreado via `data-tocado` no JS) — depois disso a escolha do usuário nunca é
  sobrescrita. Campos novos: `fixado` (bool), `preco_fixado` (decimal) e `preco_fixado_unidade`
  (`Contrato::unidadesPreco()`). O controller **limpa os campos do modo não usado** ao salvar
  (fixado → zera diferencial/mes_fixacao; não fixado → zera preco_fixado/preco_fixado_unidade),
  pra não deixar dado morto de um modo vazando no outro. A lista "Contratos gerados" e a tela do
  contrato mostram um badge **FIXED** (verde) ou **A FIXAR** (cinza).
- **PDF** via `barryvdh/laravel-dompdf` (`resources/views/contratos/pdf.blade.php`), fiel ao modelo —
  cláusulas fixas (SELLER, SHIPPER, PAYMENT, ARBITRATION, OTHER CONDITIONS, APPLICABLE LAW,
  DESTINATION=T.B.I) no template; assinatura com espaço amplo para **carimbo**. Arquivo:
  `UT_<num>_<CLIENTE>_<dd-mm-aaaa>.pdf`.
- **Editar contrato** (`contratos.edit/update`): o formulário virou a partial
  `contratos/_form.blade.php`, compartilhada por "Novo contrato" e "Editar contrato" (a
  partial recebe `$contrato = null` na criação). Ao salvar, sacas/lotes/containers são
  recalculados e o **snapshot de cliente/qualidade é regravado**. Duas travas:
  (1) não dá para reduzir a quantidade a ponto de o contrato ficar com **menos lotes do que
  já foram fixados** na Tela NY (`UpdateContratoRequest` simula o cálculo do model antes de
  gravar); (2) `Contrato::recalcularFixacao()` só roda **se existirem tranches** — senão ele
  zeraria um contrato marcado como FIXED na mão, já que assume as tranches como fonte da
  verdade.
- **Cancelar × Excluir contrato** — são coisas diferentes de propósito:
  - **Cancelar** (`contratos.cancelar`, PATCH): cliente desistiu. Exige **motivo**
    (`motivo_cancelamento`, com `cancelado_em` e `cancelado_por`), o registro e o PDF
    continuam disponíveis, mas o contrato **sai da posição**: `Contrato::scopeAtivos()` o
    remove da Tela NY e de todos os números do `PainelInicial`. Fixações já registradas
    **não são apagadas** — a operação de mercado aconteceu de verdade. Aparece na lista com
    badge vermelho CANCELADO e linha esmaecida; a tela do contrato mostra motivo, data e autor.
  - **Excluir** (`contratos.destroy`, DELETE): só para contrato **lançado errado**. Também
    exige motivo, que vai para o **AuditLog** — único lugar onde a informação sobrevive
    depois que o registro some. **Bloqueado quando há fixações**: `fixacoes.contrato_id` tem
    `cascadeOnDelete`, então excluir apagaria as tranches junto (perda de registro de
    operação real); nesse caso a tela orienta cancelar. O botão já vem desabilitado.
  - **Reativar** (`contratos.reativar`, PATCH): desfaz um cancelamento feito por engano.
    Também exige motivo. O contrato volta para a posição (Tela NY e painel) e os campos de
    cancelamento são limpos — a história não se perde porque o AuditLog guarda tanto o
    cancelamento quanto a reativação, e a descrição da reativação **repete o motivo do
    cancelamento anterior**. O formulário fica dentro do próprio aviso vermelho, num
    `<details>` ("Foi cancelado por engano?").
  - Cancelar e excluir ficam numa "zona de risco" (`<details>` fechado por padrão) no rodapé
    da tela do contrato, cada uma com seu campo de motivo e `confirm()`. As três ações usam
    o mesmo `MotivoContratoRequest` (motivo obrigatório, 5–500 caracteres).
- **Link compartilhável com botão "Copiar"** (`partials/flash.blade.php`): usa a Clipboard
  API quando disponível e cai para `execCommand('copy')` via textarea temporária fora de
  contexto seguro; dá feedback "Copiado!" no próprio botão.
- **Snapshot**: grava nome/endereço do cliente e descrição da qualidade na criação → editar o
  cadastro depois **não altera** contratos antigos (na edição o snapshot é regravado, ver acima).
- **Lista** de contratos gerados (`contratos.index`) com re-download do PDF. **Nº UT** manual e único.
- **Listas fixas** em `Contrato::`: certificados (Sem cert., 4C, EUDR, RFA, 4C+EUDR, RFA+EUDR),
  embalagens (**Bulk Liner, Jute Bags, Jute Bags 59kg, Big Bag, Jute + Grainpro**), incoterms (FOB),
  portos (Santos/Vitória), meses (Santos NY / Vitória Londres).
- Testes: `tests/Feature/ContratoTest.php` — cálculos, arredondamento, saca 59kg, containers 20'/40',
  preço por porto (NY/Londres), unicidade do UT, snapshot e geração do PDF (25 testes no total, verdes).

### Módulo 3 — Mercado (Tela NY + Cotações)
- **Tela NY** (`/tela-ny`, perfis admin/compras): fixação de preço dos contratos A FIXAR,
  **por lotes (tranches)**. Cada fixação registra corretora, broker do cliente (opcional),
  **tela** (posição da bolsa contra a qual se fixa — códigos de
  `Contrato::mesesFixacaoSantos()/Vitoria()`, validada contra a bolsa do contrato), nº de
  lotes, level e diferencial; o **preço da tranche (level + diferencial)** é calculado no
  servidor (`Fixacao::booted()`). **Fixação em grupo**: o formulário usa checkboxes — 
  marcando vários contratos (obrigatoriamente da mesma bolsa; o JS trava os de bolsa
  diferente), fixa **todos os lotes restantes de todos de uma vez**, com o mesmo
  level/corretora/tela e o **diferencial de cada contrato** (editável por linha); tudo numa
  transação (ou grava tudo, ou nada). Fixação **parcial** só no modo de 1 contrato. Quando a soma dos lotes fixados atinge os lotes do contrato, o
  contrato **vira FIXED automaticamente** com preço = **média ponderada** das tranches
  (`Contrato::recalcularFixacao()`), na unidade da bolsa do porto (Santos → cts/lb,
  Vitória → USD/MT). Excluir uma tranche recalcula e pode **reverter FIXED → A FIXAR**.
  Contratos criados manualmente como FIXED não têm tranches e não passam por esse fluxo.
  Fixações aparecem na tela do contrato; badge **PARCIAL n/m** (âmbar) nas listas
  (`withSum('fixacoes as lotes_fixados', 'lotes')` para evitar N+1). AuditLog registra
  fixação criada/excluída. **Corretoras e brokers dos clientes**: cadastro gerenciado pelo
  admin em **Administração → Corretoras** (`admin.corretoras.*`, model `Corretora`, tabela
  `corretoras` com `tipo` NOSSA/CLIENTE) — deixaram de ser listas fixas no código porque os
  brokers dos clientes mudam o tempo todo. A fixação grava o **nome como snapshot**
  (`fixacoes.corretora`/`broker_cliente` são strings): renomear/excluir um cadastro não
  altera fixações antigas. A validação usa `Rule::exists('corretoras','nome')` filtrado
  por tipo. A migration `..._create_corretoras_table.php` semeia as listas que eram
  fixas e converte os códigos antigos ('STONEX') para nomes nas fixações existentes.
- **Posição de fixações por tela** (card no topo da Tela NY): para cada tela, os lotes/
  sacas **a fixar** (contratos pendentes agrupados pelo `mes_fixacao` previsto; sacas =
  lotes restantes × divisor do tipo de café) e os lotes **fixados** naquela tela com o
  **level médio ponderado**. Formato espelhado da aba "Resumo FOB → VENDAS À FIXAR POR
  REFERÊNCIA" da planilha de posição da mesa. Contrato sem `mes_fixacao` entra como
  "Sem tela definida". Cálculo em `FixacaoController::posicaoPorTela()`.
### Embarques — agenda das estufagens e vendas em aberto (nov/2026)
**Passo 1 de 4 para tirar a posição Long & Short da planilha.** A análise
completa da planilha de posição (23 abas) está resumida na seção "Roteiro da
posição" mais abaixo; aqui fica o que foi construído.

- **`embarques`** = a PARCELA do contrato que sai num embarque. Substitui dois
  controles que repetiam o contrato fora do sistema: a aba `VENDAS-FOB` da
  planilha de posição e a planilha do Google com a agenda das estufagens. A
  maioria dos contratos tem um embarque só; alguns são divididos (UT 5973 A,
  B, C, D). O **mês de faturamento é da parcela**, não do contrato: uma
  parcela pode rolar para o mês seguinte sem as outras.
- **`contratos.standard`** = o balde da posição (17/18 FC, 17/18 GC, 14/16 FC,
  14/16 GC, Grinders 12UP/13UP — `Contrato::standards()`). **Não é a descrição
  comercial da qualidade.** Opcional: venda fora da posição (black beans,
  moka) fica sem, e a tela avisa.
- **A venda sai da posição na BAIXA do estoque, não na nota** (17/11/2026 —
  corrige a regra anterior, "faturar tira da posição no ato"). A mesa: *"fatura
  e dar baixa são coisas diferentes; os que foram dado baixa são os que
  registraram a saída do estoque"*. Entre a nota e a baixa o café continua no
  armazém e conta no estoque físico do Digisul; se a venda sumisse na nota, a
  posição ficaria mais comprada do que é. Então:
  - `embarques.baixado_em` (data, migration `2026_04_01_000003`). **É uma data,
    não uma etapa**: a baixa não tem ordem fixa com a nota, por isso não entra
    em `Embarque::statuses()`. `faturado_em` continua acompanhando o status.
  - `Embarque::baixado()`, `aguardandoBaixa()` (faturado e ainda no estoque) e
    os scopes `semBaixa`, `baixados`, `faturadosSemBaixa`. **Método e scope
    não podem ter o mesmo nome** — `aguardandoBaixa()` + `scopeAguardandoBaixa`
    quebra com "Non-static method cannot be called statically".
  - **Controle logístico**: card "Faturados aguardando baixa de estoque" (a
    fila, sempre visível, com data + botão "Dar baixa" → `embarques.baixar`),
    filtros "Faturados aguardando baixa" e "Baixados", e a baixa aparece
    embaixo da etapa. A linha só fica riscada depois da baixa. Desfazer a
    baixa = apagar a data no formulário do embarque (fica no log de auditoria).
  - `encerrado()` / `scopeEmAberto` continuam significando "faturado ou não" —
    é o que a AGENDA usa (o que ainda vai ser estufado).
  - **Baixas trazidas do Digisul** (17/11/2026, `importar_baixas.php` em
    `storage/app/private/`): o relatório "embarcados" (texto separado por
    `|`) casa por UT + sufixo, com as sacas iguais. Linha de contrato que não
    está no sistema é ignorada (vendas anteriores à carga), e também a de
    parcela antiga (a "A" de um contrato cujo saldo aqui é a "B").
    **Cuidado com a unidade**: o Digisul conta SACOS — numa venda em sacos
    de 30 kg ele mostra o dobro das sacas de 60 kg do sistema; o peso é o
    mesmo. Divergência de sacas não é gravada, só listada.
- **Vendas em aberto por mês × standard** (`App\Services\PosicaoVendas`), no
  topo da agenda: embarques **sem baixa**, no mês de cada um, **mais o saldo
  do contrato que ainda não virou embarque**, no mês de embarque do contrato.
  Sem a segunda parte a venda recém-lançada ficaria fora da posição. Sobra
  de menos de 1 saca é arredondamento de peso e não entra. Nada some em
  silêncio: contrato sem standard ou sem mês ganha linha/coluna própria e
  aviso; mês passado ainda aberto vira coluna "Atrasado".
- **Quantidade não é travada no contrato** (mesma regra das entregas):
  container não pesa exatamente o contratado; a tela do contrato mostra a
  diferença.
- **Área de EXPORTAÇÃO no menu** (pedido da mesa: a logística não é
  "Contratos" — o contrato é o negócio fechado, a exportação é o que acontece
  depois). Duas vistas dos mesmos embarques, leitura para todo perfil e
  alteração só de admin/compras:
  - **Agendamentos** (`embarques.agenda`, `/exportacao/agendamentos`): os
    embarques em aberto **por dia de estufagem** — a planilha do Google.
    Dia vencido ou a 3 dias ganha etiqueta; embarque sem data vai para a
    lista "a agendar", para não sumir.
  - **Controle logístico** (`embarques.index`, `/exportacao/logistica`): por
    mês de faturamento, com PSS, etapa, prazos, navio, o botão Faturar, a
    grade de vendas em aberto e os contratos com volume a programar.
- **Carga inicial das vendas** (nov/2026): scripts em `storage/app/private/`
  (fora do Git, dados reais) — `extrair_vendas.py` lê as duas planilhas e
  gera um JSON; `importar_vendas.php` simula por padrão e só grava com
  `APLICAR=1`. Contrato existente não é alterado (só ganha standard e
  embarques se não tiver).
- Excluir contrato com embarque é **bloqueado** (a FK é cascata, como nas
  fixações).
- **Programação de estufagem** (nov/2026): o e-mail que a logística manda ao
  armazém e à transportadora a cada embarque, antes montado à mão. Saiu em
  duas peças, por decisão da mesa:
  - **PDF** (`embarques.programacao`, paisagem): a tabela de datas (fumig.,
    stuff, deadlines, phyto, armazém, transportadora, destino, ETD, navio,
    booking, ICO, qtd, packing, pilha), os avisos fixos da logística, as
    instruções do embarque em destaque e a segunda tabela, da fumigação
    (só quando há data de fumigação). **É PDF e não e-mail formatado porque
    `mailto:` só leva texto puro — e o armazém imprime a programação.**
  - **E-mail por `mailto:`** (`App\Services\ProgramacaoEstufagem`): assunto
    no formato da equipe ("PROGRAMAÇÃO ESTUFAGEM: UT … - BOOKING: … -
    ARMAZEM: …"), um resumo em texto e a assinatura do usuário. O PDF é
    anexado por quem envia (link não anexa arquivo).
  - **Destinatários**: `armazens.emails_programacao` + os e-mails da
    transportadora, achada pelo NOME gravado no embarque. Cópia fixa em
    `EXPORTACAO_COPIA_FIXA` (`config/exportacao.php`). Sem destinatário o
    botão de e-mail some e a tela diz o que falta cadastrar.
  - **Cadastro de transportadoras** (Cadastros → Transportadoras). O
    embarque guarda o nome como TEXTO (mesma lógica da corretora na
    fixação); o cadastro sugere o nome e dá os e-mails.
  - Campos novos no embarque: `ico`, `fumigacao_empresa/_tipo/_tempo`,
    `permit_import`, `instrucoes`.
  - Testes: `tests/Feature/ProgramacaoEstufagemTest.php` (13).
- As etapas (`Embarque::statuses()`) e as situações da PSS são listas no
  model — foram definidas a partir dos textos livres das duas planilhas e
  **vale a mesa revisar**.
- Testes: `tests/Feature/EmbarqueTest.php` (29), com o tempo congelado.

- **Fixações trazidas da planilha** (17/11/2026, `sincronizar_fixacoes.php`
  em `storage/app/private/`): 75 tranches criadas para os contratos que a
  aba VENDAS-FOB dava como Fixed, com a data, o level, o diferencial e a
  tela de cada um. A planilha não diz a corretora, então entraram como
  **"Não informada (planilha)"**. Contrato que já tinha tranche lançada à
  mão não foi tocado. Depois disso a "Posição de fixações por tela" bate
  com a planilha, com duas diferenças conhecidas: a UT 5895 (metade fixada,
  metade a fixar — a planilha divide por sacas, o sistema por lotes
  inteiros: 120 sc) e as UT 5995/5996/5997, que só existem no sistema.
- **Posição por tela em SACAS REAIS** (`Contrato::sacasDeLotes()`): a tela
  fazia "lotes × 283,49" e ponderava o level médio pelos lotes inteiros. Com
  contratos pequenos (320 sc = 1,13 lote = 1 lote) isso dava ~170 sc a menos
  a fixar na H7 e mais de 1 cent de diferença no level médio em relação à
  planilha. Agora as sacas são as do contrato, na proporção dos lotes, e o
  level médio é ponderado por elas; a tela ganhou a coluna "Fixado (sacas)".
- "Fixações registradas" ordena pela **data da fixação**, não pelo id.

#### Roteiro da posição (decidido em nov/2026)
1. ~~Embarques~~ (feito, acima). ~~Carga das vendas em aberto~~ e ~~carga
   das compras~~ **feitas em 17/11/2026**, a partir da planilha de posição
   de 16/11 e da planilha fiscal. Depois da carga a posição de vendas do
   sistema bate com a da planilha; com a regra da baixa ela passa a incluir
   o que está faturado e ainda sem baixa, que a planilha já não contava.
2. **Importação do Digisul.** *(A baixa das VENDAS já é controlada no
   sistema — ver "A venda sai da posição na BAIXA". O que falta importar é o
   estoque físico por lote e o saldo inicial de 31/10.)* O Digisul emite a
   NF-e e dá a baixa, então ele
   continua sendo a verdade do estoque físico — o sistema IMPORTA a
   exportação `.xlsx` dele (263 lotes, com padrão, armazém, certificador,
   guia de serviço e a UTC da compra) em vez de manter um razão próprio, que
   seria o mesmo retrabalho com outro nome. O padrão do Digisul tem 11
   valores e vira os baldes por um de/para. **Atenção**: o PHP de linha de
   comando não tem a extensão `zip`, necessária para ler `.xlsx`.
3. **Long & Short calculado** = estoque (medido + rendimento estimado do que
   ainda é bica/reaproveitamento) + compras a receber − vendas em aberto.
   Na planilha, 60% do lado comprado é estimativa de rendimento. As compras
   entram no mês certo (a planilha as jogava um mês para frente — confirmado
   pela mesa como desalinhamento).
4. **Custo médio e hedge.**

### Posição Long & Short (17/11/2026) — passo 3 do roteiro
Tela **Mercado → Posição Long & Short** (`posicao.index`, `/posicao`, leitura
para todo perfil). É a aba POSIÇAO_LONG SHORT da planilha, calculada em
`App\Services\LongShort`. Por standard, em sacas:

    foto do estoque (medido + rendimento estimado da bica/reaproveitamento)
    + entregas a partir da data da foto − baixas a partir da data da foto
    = ESTOQUE HOJE  + compras a receber  = DISPONÍVEL
    − vendas em aberto mês a mês (PosicaoVendas)  = POSIÇÃO (long/short)

- **Foto do estoque** (`fotos_estoque` + `foto_estoque_lotes`, model
  `FotoEstoque`): o estoque do Digisul lote a lote, com DATA. O estoque
  físico continua sendo do Digisul; o sistema parte da foto e anda sozinho
  com as entregas e as baixas posteriores. **A foto vale como abertura do
  dia** (entrega/baixa do próprio dia conta como posterior). Foto nova só
  re-ancora — `FotoEstoque::atual()` pega a mais recente.
- **Balde** de cada lote = de/para do padrão do Digisul
  (`FotoEstoque::baldeDoPadrao`): a coluna que a mesa preenchia à mão na aba
  GERAL ESTOQUE. Padrão desconhecido fica SEM balde e a tela avisa — não é
  chutado. Bica FC/GC e reaproveitamento viram padrão pelo rendimento de
  `config/posicao.php` (os percentuais do rodapé da aba).
- **Rendimento da compra** (`compras.pct_1718`, `compras.pct_cata`,
  `Compra::rendimentoPrevisto()`): 17/18 = %; 14/16 = 100 − 17/18 − cata; da
  cata, 85% grinders e 15% mercado interno (`posicao.grinders_sobre_cata`).
  Ordem: % da compra → classificação → rendimento padrão da família (com
  aviso). Campos no formulário da compra. **A divisão da cata segue a
  planilha (85/15), não a peneira do Digisul (60/40).**
- **Diferenças para a planilha, de propósito**: nada é apagado à mão (a
  data da foto decide o que entra); as compras entram já no disponível (na
  planilha caíam um mês para a frente — desalinhamento confirmado); venda
  atrasada pesa no primeiro mês.
- **Mercado interno** aparece só do lado comprado: as vendas de mercado
  interno ainda não estão no sistema.
- **Carga inicial** (`importar_posicao.php` em `storage/app/private/`): foto
  de 03/11/2026 a partir da aba GERAL ESTOQUE (266 lotes; o de/para do
  sistema bateu com o da mesa em todos) e % de 17/18 e cata das 37 compras.
  A posição do sistema fecha com a da planilha, com duas diferenças
  explicadas: uma UTS que a planilha conta duas vezes (já está na foto e
  ainda aparece como saldo a faturar) e as UT 6025/6026 (sacas digitadas em
  dobro na planilha). Os números estão no relatório da carga, fora do Git.
- **Falta**: tela para subir a foto nova do Digisul (hoje é por script — o
  PHP não tem a extensão `zip` para ler `.xlsx`); vendas de mercado interno;
  custo médio e hedge (passo 4).
- Testes: `tests/Feature/LongShortTest.php` (10).

### Carta de fixação — PRICE FIXING CONFIRMATION (set/2026)
Depois de cada fixação o cliente manda a carta de confirmação dele (modelo
analisado: a da Alfa para a UT 5970). Agora a Union emite a sua:
**Contrato → "Carta de fixação (PDF)"** (`contratos.carta-fixacao`), botão
que só aparece quando há ao menos uma tranche.

- **Dois números que não se confundem**: **Average** é a média dos `level`
  (o preço da bolsa) e **Final Price** é a média dos `preco` das tranches
  (level + diferencial). Na carta real: 289,50 de média, -10 de diferencial
  e 279,50 de preço final. Ambas **ponderadas pelos lotes** — a simples
  trataria uma tranche de 1 lote igual a uma de 3. Em
  `Contrato::resumoDeFixacao()`.
- **Coluna Balance**: saldo de lotes correndo depois de cada tranche, como
  na carta do cliente.
- **Sai também com fixação PARCIAL**, porque a carta é a confirmação de cada
  fixação e a do cliente também chega com saldo em aberto. Nesse caso um
  aviso diz quantos lotes faltam, senão o "Final Price" passaria por preço
  fechado.
- **Somos o SELLER**: `Seller's Ref.` é o nosso UT e `Buyer's Ref.` é o
  `buyer_ref`. Na carta que o cliente manda eles aparecem trocados de lado,
  porque lá quem escreve é o comprador — copiar sem pensar inverteria as
  referências no documento que vai para fora.
- **Posição por extenso em inglês** (`Contrato::bolsaEMesPorExtenso()`):
  `H7` => "ICE NEW YORK / March,2027", `Sep_2026` => "ICE LONDON /
  September,2026". O ano cheio de Santos é recuperado do último dígito, o
  que só funciona porque a janela conhecida tem menos de 10 anos — dentro
  dela cada dígito aparece uma vez só. Código irreconhecível volta cru, em
  vez de virar posição inventada num documento externo.
- Testes: `tests/Feature/CartaFixacaoTest.php` (10). **Armadilha encontrada
  ao escrever**: `contratos.lotes` é CALCULADO no `saving` a partir de
  `quantidade_kg`, então `update(['lotes' => 4])` é ignorado em silêncio e
  dois testes passaram por acaso. O helper `comLotes()` mexe no peso e
  **confere** o resultado antes de seguir.

### Contratos a fixar: coluna da tela e filtros (set/2026)
- A fila do que falta fixar ganhou a coluna **Tela** com o código curto
  (`H7`, `Z6`) — é como a mesa fala; o mês por extenso fica no `title`.
- Filtros de **UT** (busca parcial), **Comprador** e **Mês de fixação**. As
  opções de comprador e de tela saem dos **contratos pendentes**, não das
  listas gerais: oferecer uma posição sem nenhum contrato atrás é clique
  perdido. Contrato sem mês entra como **"Sem tela definida"**
  (`FixacaoController::SEM_TELA`), o mesmo código que a posição por tela usa.
- **A "Posição de fixações por tela" NÃO é filtrada junto.** Ela é um retrato
  geral; encolher o card com a tabela deixaria uma posição parcial com cara
  de total — erro que ninguém percebe olhando. Por isso o controller faz duas
  consultas: `contratosPendentes()` sem filtro alimenta o card, e com filtro
  alimenta a tabela. Guardado por teste.
- **Dois conjuntos de filtro na mesma tela** (`FILTROS_CONTRATOS` para a fila,
  `FILTROS` para as fixações). Cada formulário leva o filtro do outro em
  campos `hidden`, e o "Limpar" de cada um preserva o do vizinho — senão
  filtrar embaixo apagaria o recorte de cima. Cada form volta para a sua
  âncora (`#contratos` / `#fixacoes`).

### Fixações registradas: página própria (set/2026)
O histórico saiu da Tela NY e virou item de menu em **Mercado → Fixações
registradas** (`ny.fixacoes.index`). Motivo: a Tela NY é onde se **faz** a
fixação, e a lista do que já foi feito é consulta — com os dois filtros e as
duas tabelas na mesma tela, a página ficou longa e dividia a atenção de quem
estava operando. A Tela NY ficou com três cartões: posição por tela,
contratos a fixar e o formulário.

Efeito colateral bom: sumiu a ginástica dos campos `hidden` que cada
formulário levava para não apagar o filtro do vizinho — cada página tem o
seu. O `store()` continua voltando para a Tela NY (é de lá que se fixa), e o
aviso com a instrução de AA continua aparecendo lá, porque o
`partials/flash` mora no layout.

### Fixações registradas: filtro e PDF (set/2026)
- Filtro por **corretora** e por **contrato** na lista da Tela NY. As opções
  saem das **próprias fixações**, não do cadastro: opção sem nenhum registro
  é clique perdido, e o histórico pode ter nome de corretora que já saiu do
  cadastro (a fixação guarda o nome como snapshot).
- **O filtro vai ao banco, não à lista já carregada.** Sem filtro a tela
  mostra as **últimas 15**; se o filtro fosse aplicado sobre essas 15, uma
  tranche antiga daquele contrato — justamente a que se procura — nunca
  apareceria. Com filtro ativo, some o corte e aparece o recorte inteiro.
- **PDF** (`ny.fixacoes.pdf`) com o **mesmo recorte da tela**, e sem o corte
  das 15: quem baixa quer o conjunto do filtro, não uma amostra. Os filtros
  ativos são impressos no cabeçalho — relatório filtrado que não diz o
  recorte vira número sem contexto na mão de quem recebe. Paisagem, porque
  retrato não cabe as 10 colunas. Os filtros ficam em
  `FixacaoController::FILTROS`, lidos por tela e PDF pelo mesmo método.
- **Totais por UNIDADE, nunca um total só**: Santos é cts/lb e Vitória é
  USD/MT; somar ou mediar os dois daria um número que parece certo e não
  significa nada. O level médio é **ponderado pelos lotes** (a média simples
  trataria uma tranche de 1 lote igual a uma de 12).
- Testes: `tests/Feature/FixacoesFiltroPdfTest.php` (8) — inclusive o caso
  da tranche fora das 15 e a conferência da média ponderada.

### Instruções de AA por e-mail (set/2026)
Depois de fixar, a mesa manda ao broker a **instrução de AA** ("against
actuals"). Isso vivia na planilha `AA instructions v2.xlsm`, cuja macro
montava a mensagem e abria o Outlook com `.Display` — **não enviava**, só
deixava pronto para conferir e clicar em Enviar.

- **No sistema é um `mailto:`**, não envio pelo servidor. Escolha do projeto:
  o e-mail sai da **caixa de quem fixou**, então a resposta do broker volta
  para a pessoa, e não para uma caixa do sistema; e não depende dos `MAIL_*`
  de produção, que seguem pendentes. Preço do não escolhido: texto puro, sem
  anexo e sem registro do que foi enviado — se um dia isso fizer falta, o
  caminho é uma `Mailable` ao lado, reaproveitando o mesmo `InstrucaoAA`.
- **O PREÇO da instrução é o `level`, não o `preco` da tranche**: o AA se faz
  contra a bolsa, e o diferencial é o prêmio do físico. Guardado por teste,
  porque é o tipo de erro que ninguém percebe olhando a tela.
- **Uma instrução por CLIENTE.** O e-mail é sempre "Union X um cliente", mas a
  fixação em grupo pode misturar clientes. Por isso `fixacoes.grupo` (uuid
  gravado no envio do formulário): ele diz quais tranches são o mesmo negócio,
  e `InstrucaoAA::deFixacoes()` separa por cliente. Fixar 2 contratos da Alfa
  e 1 da Beta de uma vez = dois e-mails, cada um com os seus contratos —
  na planilha essa separação era feita na mão, contrato por contrato.
- **Commodity/Month** vem de `Contrato::commodityDaTela()`: Santos vira `KC` +
  tela (`Z6` => `KCZ6`) e Vitória vira `RC` + letra do mês + último dígito do
  ano (`Sep_2026` => `RCU6`) — a tela de Londres é `Mmm_AAAA` por dentro, para
  ficar legível no formulário, e precisa ser traduzida para a forma da bolsa.
- **Destinatários viraram cadastro** (eram duas abas da planilha):
  `corretoras.emails_para` / `emails_copia` (a NOSSA corretora define quem
  recebe, porque é ela que executa o AA) e `clientes.emails_copia`, que soma
  na cópia. Lista separada por `;`, com `App\Rules\ListaDeEmails` sendo a
  única definição do formato — a validação do cadastro e a leitura pelos
  models usam o mesmo `separar()`, senão daria para salvar uma lista que o
  envio interpretaria de outro jeito. Corretora sem e-mail não mostra o botão
  e explica onde cadastrar (a macro dava um `MsgBox` equivalente).
- **Cópia fixa da casa**: `AA_COPIA_FIXA` no `.env` (lido por `config/aa.php`)
  entra em cópia em TODA instrução, independente de corretora e cliente.
  Fica no `.env` e não no código porque o repositório é **público** e a lista
  muda com a equipe, sem deploy. Quem já está no "Para" **não é repetido** na
  cópia (comparação sem diferenciar maiúsculas) — receberia o mesmo e-mail
  duas vezes. Hoje é um endereço que também aparece nas listas "Para" das
  duas corretoras; por isso, na prática, ele sai como destinatário e a cópia
  fixa só entra em cena se um dia a lista da corretora não o incluir.
- **A assinatura é do usuário logado** (`users.cargo`, `cargo_en`, `telefone`,
  `celular`, editáveis em Administração → Usuários). Na macro ela estava
  escrita no código, então qualquer pessoa que apertasse o botão mandava o
  e-mail assinado com o nome de quem montou a planilha. Campo em branco
  simplesmente não aparece.
- **Onde aparece**: logo depois de registrar a fixação (um botão "Abrir no
  Outlook" por cliente, com "Copiar texto" ao lado) e na coluna de ações de
  "Fixações registradas", para remontar depois. O botão da lista usa o
  **grupo inteiro**, não a linha.
- **"✓ Instrução enviada"** tira o item do aviso depois de enviado (e o aviso
  inteiro quando sai o último), para o que já foi feito parar de pedir
  atenção. É só visual: o aviso é flash e sumiria na página seguinte de
  qualquer jeito — por isso o botão nasce `hidden` e só aparece com JS, em
  vez de virar um botão morto sem ele. **Não fica registrado** que a
  instrução foi enviada; se um dia isso fizer falta (saber quais fixações já
  tiveram AA mandado), é coluna em `fixacoes` + rota, não mais um `hidden`.
- **O Outlook resolve os endereços preguiçosamente** e os últimos da lista
  chegam como texto solto, não como destinatário validado — a pessoa acaba
  clicando um a um. Um link não tem como chamar o `Recipients.ResolveAll`
  que a macro usava. **Decisão (set/2026): fica assim**, com a dica de
  `Ctrl+K` ("Verificar Nomes") escrita no próprio aviso, que resolve todos
  de uma vez. A alternativa avaliada e **não** adotada era trocar o
  `mailto:` por download de um `.eml` com `X-Unsent: 1`, que o Outlook abre
  como rascunho já com os destinatários montados dos cabeçalhos (e de quebra
  mataria o limite de tamanho abaixo) — se o `Ctrl+K` cansar, é por aí.
- **Limite do `mailto:`**: o Outlook corta a URL perto de 2.000 caracteres
  (`InstrucaoAA::LIMITE_MAILTO`). Um AA comum dá ~720; muitos contratos numa
  instrução só podem chegar lá, e nesse caso a tela avisa e sobra o "Copiar
  texto". É limite do Windows/Outlook, não do sistema — daí ser aviso e não erro.
- **Números saem no padrão brasileiro** (`Price: 271,25`), que é o que o
  `Format` do VBA produz nas máquinas da mesa e o que os brokers já recebem
  hoje. **Ponto em aberto**: para robusta em USD/MT (`3.725,00`) isso é
  ambíguo para quem lê em inglês — se for para trocar por `3,725.00`, é uma
  linha em `InstrucaoAA::corpo()`.
- Testes: `tests/Feature/InstrucaoAATest.php` (16 testes) — formato do corpo,
  price = level, separação por cliente, destinatários somados, cópia fixa e
  não-repetição, aviso sem cadastro, tradução da tela de Londres, `mailto`
  com `%0D%0A`, assinatura do usuário, botão na tela e nas telas de cadastro.
  O `setUp` zera a cópia fixa de propósito: senão o resultado mudaria
  conforme o `.env` de quem roda os testes.

- **Cotações** (`/mercado`, todos os perfis): arábica NY (7 posições), robusta Londres e
  câmbio (dólar/euro) via **Yahoo Finance** (endpoint público `v8/finance/chart`, delay
  ~15 min, sem chave de API). Backend em `app/Services/MercadoCafe.php`: busca os símbolos
  em paralelo (`Http::pool`), **cache de 30s** do snapshot + cache de longa duração por
  símbolo ("último valor conhecido", devolvido com `stale=true` se o Yahoo falhar);
  posição sem dado algum sai `price=null` e o front mostra "indisponível".
  JSON em `GET /api/market` (autenticado); a página re-busca a cada 30s e, se a conexão
  cair, mantém os últimos valores com aviso. A Tela NY tem uma régua de cotações no topo
  (melhor esforço — some se a API falhar).
- **Validado contra o Yahoo real** (10/ago/2026): câmbio OK (`BRL=X`, `EURBRL=X`) e as 7
  posições do arábica OK (`KCU26.NYB` ... `KCZ27.NYB`). **Robusta Londres NÃO existe no
  Yahoo Finance** (nenhum ticker; a busca retorna zero) — as 4 posições ficam
  "indisponível" até trocarmos a fonte (as listas de símbolos ficam em
  `MercadoCafe::ARABICA/ROBUSTA/CAMBIO`, fáceis de editar). Obs.: Londres não tem contrato
  de dezembro — meses do robusta são F/H/K/N/U/X (X = novembro).
- Testes: `tests/Feature/FixacaoTest.php` (preço no servidor, parcial → FIXED com média
  ponderada, limite de lotes, exclusão reverte, 403) e `tests/Feature/MercadoTest.php`
  (formato do JSON, fallback stale, indisponível, auth) — com `Http::fake`. Cuidado:
  chamadas sucessivas de `Http::fake` NÃO substituem a anterior (stubs se acumulam e o
  primeiro match vence) — para simular queda use um fake único com closure + flag.

### Módulo 4 — Mensagens (canal da equipe)
- **Mural interno**: um canal só, onde **todo perfil lê e escreve** (os perfis do
  sistema limitam o que se ALTERA nos registros, não a conversa da equipe).
  Rota `/mensagens` (`mensagens.*`), item próprio no topo do menu com **badge de
  não lidas**.
- **Sem WebSocket, de propósito.** A tela pergunta por mensagens novas a cada 10s
  (`GET /mensagens/novas?depois=<id>`), no mesmo espírito da página de Cotações
  com `/api/market`. Tempo real de verdade (Reverb/Pusher) exige um **processo
  rodando sempre** ao lado do PHP — o projeto não tem nem worker de fila, e o
  ganho de alguns segundos não paga o custo de deploy. Se algum dia virar
  necessidade, o caminho é trocar só a camada de transporte: o resto (mensagens,
  não lidas, permissões) fica igual.
- **Não lidas por marca de leitura, não por tabela de leituras**:
  `users.mensagens_lidas_em` guarda quando o usuário abriu o canal; não lidas =
  mensagens **de outros** criadas depois disso (a própria nunca conta). Com um
  canal só, tabela pivô por mensagem seria peso sem ganho. Abrir o canal — ou
  receber pelo polling com a tela aberta — marca como lido; por isso o badge
  **não aparece na própria tela de mensagens**, e isso é o comportamento certo.
- **Apagar**: o autor apaga a própria; **admin apaga qualquer uma**, e nesse caso
  o texto vai para o **AuditLog** — único lugar onde ele sobrevive depois que a
  linha sai do canal.
- **Texto do usuário nunca vira HTML**: Blade escapa na renderização e o JS que
  insere as mensagens novas usa **`textContent`** (nunca `innerHTML`). Verificado
  no navegador com uma mensagem contendo `<img onerror=...>`: nenhuma tag é
  criada e o script não roda. Guardado também por teste
  (`MensagemTest::test_texto_do_usuario_nao_vira_html`).
- **TEXTO CIFRADO NO BANCO** (cast `encrypted`, AES-256 com a `APP_KEY`).
  Conferido no MySQL real: a coluna guarda o payload base64 (iv/value/mac) e não
  contém nenhum trecho legível.
  - **Protege**: dump/backup do banco e acesso só ao MySQL — que é por onde dado
    vaza na prática.
  - **Não protege**: quem tem servidor **e** chave lê tudo (a tela precisa
    descriptografar), nem o **trânsito** — isso é papel do HTTPS, que segue como
    item de deploy no `SECURITY.md`.
  - **Duas consequências assumidas**: a `APP_KEY` fica **insubstituível** (perdê-la
    ou trocá-la = perder as mensagens antigas — o backup dela deixa de ser
    detalhe), e **não existe busca por conteúdo em SQL** (`LIKE` em cifrado não
    acha nada; se um dia precisar de busca, tem de ser filtro em memória).
  - Por isso a coluna virou `TEXT` (o cifrado de 2.000 caracteres não caberia em
    `VARCHAR(2000)`) e o log de auditoria de exclusão **deixou de guardar o
    texto**: cópia em claro no log seria porta dos fundos para o que a
    criptografia veio proteger. O log registra quem apagou, de quem e quando.
- **Menção com @nome** (`mensagem_mencoes`): citar alguém marca a mensagem
  ("citou você"), destaca o nome no texto e troca o badge do menu por **`@n`** com
  cor invertida — ser chamado pelo nome é aviso mais forte do que "tem mensagem
  nova". O autocomplete abre ao digitar `@` (setas + Enter escolhem) e **não abre
  no meio de palavra**, senão todo e-mail digitado abriria a lista.
  - **Um algoritmo só** (`Mensagem::analisar()`) alimenta o destaque na tela e a
    detecção de quem foi citado. Antes eram dois (um andava pelo texto
    consumindo o trecho, o outro usava `str_contains`) e eles **discordavam**:
    "@Ana Clara" destacava Ana Clara e avisava também a Ana. O teste
    `test_mencao_casa_o_nome_mais_longo` guarda isso.
  - A comparação é contra os **nomes cadastrados** (não um regex de "@palavra"),
    porque nome de gente tem espaço: "@Luiz Carlos" é uma menção só, e "@luiz"
    (primeiro nome) também acha. Usuário **suspenso** sai do autocomplete.
  - As menções são gravadas pelo **model** (evento `saved`), não pelo controller:
    assim mensagem criada por qualquer caminho (seeder, gerador da demo, import
    futuro) já nasce com as menções certas.
- Detalhes de tela: separador por dia, linha **"novas mensagens"** onde o usuário
  parou, minhas mensagens espelhadas, Enter envia / Shift+Enter pula linha,
  campo que cresce até 160px, "carregar mensagens anteriores" (50 por vez) e
  rolagem automática **só quando o usuário já estava no fim** (senão a tela pula
  debaixo de quem está lendo o histórico). Sem JS, o formulário posta normal.
- **Na demo** a tela entra como ilustração (conversa fictícia de dois dias): o
  envio precisa de backend.

#### Mensagens: emojis, reações e chat flutuante (17/11/2026)
- **Emojis no campo de texto**: botão dentro do campo abre um painel com
  uma lista curta (rostos, gestos, trabalho, café/logística) e insere na
  posição do cursor. Sem biblioteca externa. Emoji novo demais não aparece
  no Windows 10 (vira um quadrado) — a lista só tem os que ele desenha.
- **Reações** (`mensagem_reacoes`, `MensagemReacao`): uma linha por
  (mensagem, pessoa, emoji). **Lista fechada** (`Mensagem::REACOES`),
  validada no servidor — reação não é campo de texto livre. Clicar de novo
  tira (`alternarReacao`). `POST mensagens/{mensagem}/reacoes`.
  Reação em mensagem antiga não é "mensagem nova": o polling manda
  `visiveis_de` e recebe as reações do trecho aberto (com teto de 300).
- **Chat flutuante** (`partials/chat-flutuante`, incluído no layout): ícone
  no canto de toda tela, menos na de Mensagens. Abre um painel pequeno com
  o mesmo chat; lembra aberto/fechado ao trocar de tela (`sessionStorage`);
  Esc fecha. Fechado, só consulta `mensagens/resumo` a cada 30 s (o
  contador) — **olhar o contador não marca como lido; abrir o painel, sim**.
- **Uma caixa, um script**: `mensagens/_caixa.blade.php` (marcação) e
  `mensagens/_script.blade.php` (`UTChat.iniciar`) servem a tela cheia e o
  flutuante. Os elementos são achados por `data-chat-*`, não por id.
- Apagar mensagem agora é por `fetch` (some no lugar, sem recarregar).
- Testes: `tests/Feature/MensagemReacaoTest.php` (10).

### Cadastro de armazéns (ago/2026) e o armazém previsto na compra
- **Armazéns deixaram de ser ENUM no código e viraram CADASTRO** (`armazens`:
  nome único, cidade, estado, endereço opcional e **CNPJ opcional** — validado
  pelo mesmo `DocumentoValido` do fornecedor quando preenchido). Tela em
  **Cadastros → Armazéns** (`admin.armazens.*`), com edição na própria linha.
- A entrega aponta para o **cadastro** (`entregas.armazem_id`), e não guarda o
  nome como snapshot — ao contrário das corretoras. Motivo: renomear um armazém
  deve atualizar o histórico, porque é o **mesmo galpão** com nome novo;
  snapshot partiria o Estoque em dois grupos para o mesmo lugar. Consequência
  assumida: **excluir armazém em uso é bloqueado** (FK `restrictOnDelete` + aviso
  na tela, com o botão "Em uso" desabilitado); se o nome mudou, é para renomear.
- A **compra** ganhou `armazem_id` como **armazém PREVISTO** (nullable, opcional
  de propósito: às vezes o destino só se define na hora de entregar). Ele
  **pré-seleciona** o armazém no lançamento da entrega e aparece na tela da
  compra. Quem vale para o estoque continua sendo o armazém de **cada entrega** —
  o café pode chegar em outro lugar, e isso não é erro.
- O Estoque agrupa por `armazens.id` e traz `armazens.nome as armazem` pelo join,
  então as views e os testes continuam lendo `$linha->armazem` como texto (agora
  o **nome**, não o código: "QUALITÉ" em vez de "QUALITE").
- O filtro de armazém (Compras lançadas e Estoque) passou a levar o **id**. O
  link compartilhável do Estoque continua **sem** o armazém, como antes.
- Nos testes há o helper `TestCase::armazem('SAAG'|'QUALITE'|'DINAMO_MACHADO')`,
  que traduz o apelido curto para o id semeado pela migration.

### Menu lateral: Cadastros × Administração
Os cadastros ficavam num grupo só chamado "Administração", junto com Usuários —
com Armazéns entrando, a lista virava um saco de coisas soltas. Agora são dois
grupos: **Cadastros** (Clientes, Armazéns, Corretoras, Qualidades — coisas do
negócio que alimentam formulários) e **Administração** (Usuários — controle de
acesso, que é outra natureza). Optamos por **não** criar um menu "Configurações"
com submenus: acrescentaria um clique e esconderia itens que hoje estão a um
clique só. Se a lista crescer além de ~7 itens, o caminho combinado é uma
**página inicial de Configurações** com cards (cabe descrição em cada cadastro),
não submenu.

### Demo pública (GitHub Pages) — agora GERADA, não escrita à mão
- As páginas estáticas de `docs/` eram editadas na mão e por isso **atrasavam**
  em relação ao sistema (chegaram a mostrar o modelo antigo de compras, sem
  entregas nem liquidação). Agora existe um **gerador**:
  `tests/Feature/GerarDemoTest.php`. Ele monta um cenário fictício, renderiza as
  **views de verdade** com um admin logado e troca as URLs de rota por nomes de
  arquivo. Rodar assim (fora disso o teste é **pulado**, porque escreve em
  arquivo publicado):
  ```
  GERAR_DEMO=1 php artisan test --filter=GerarDemo
  ```
- **Geradas** (não editar à mão — a próxima rodada sobrescreve): `inicio.html`,
  `compras.html`, `compra.html` (UTS com divergência: entrou mais que o
  contratado, duas entregas, uma sem lote, botão **Liquidar compra**),
  `compra-liquidada.html` (aviso verde + **Reabrir**), `compra-nova.html`,
  `compra-classificacao.html` e `relatorio.html`.
- **A demo FUNCIONA de verdade no módulo de compras** (`docs/demo-compras.js`,
  ago/2026): quem abre o link consegue **lançar uma compra**, vê-la aparecer na
  lista (marcada como "sua"), **lançar/editar/excluir entregas** com a conversão
  peso↔sacas, **classificar** e **liquidar/reabrir**. O estado vive em
  **`sessionStorage`** — fecha a aba, acaba (é o que a demo promete a quem
  entra, e evita guardar dado de visitante). As UTS de exemplo continuam
  **somente leitura**; o que o visitante cria é totalmente editável. O motor
  repete as regras do servidor (60 kg/saca, saldo = contratado − entregue,
  lotes ÷ 283,49, "sem nº de lote não entra em estoque"). **A consulta de CNPJ
  não funciona** (não há backend): o campo aceita digitação e o aviso explica
  isso em vez de dar erro de rede. Como as páginas são renderizadas das views,
  o JS do próprio app (conversão de peso, % da classificação, esconder
  qualidade no conilon) já vem junto — o motor só cuida da persistência.
- **Mantidas à mão**: `index.html` (login), `tela-ny.html`, `cotacoes.html` e os
  cadastros (`clientes`, `corretoras`, `qualidades`, `usuarios`, `contratos`,
  `contrato-novo`). A Tela NY e as Cotações têm **JS de demonstração escrito na
  unha** (fixação em grupo funcionando, cotações de exemplo) que uma
  renderização crua apagaria.
- **Dados são fictícios e SEM DOCUMENTO NENHUM**: a demo é pública, então nem
  CNPJ de exemplo entra — todos os vendedores ficam "a confirmar", que é um
  estado real do sistema. Os nomes reais que ainda existiam nas páginas
  mantidas à mão (fornecedores e clientes) **foram trocados por fictícios**, e
  os `placeholder` do próprio app que citavam nomes reais também — eles vazavam
  para a demo a cada geração. O cenário foi calibrado para
  os badges do menu darem **8** (compras com pendência) e **7** (lotes a fixar),
  que são os números das páginas mantidas à mão — assim a barra lateral não
  muda de valor ao navegar. E os totais fecham entre telas:
  3.254 (estoque) + 970 (aguardando lote) + 1.020 (sem classificação) = 5.244
  sacas compradas do painel.
- Varredura de segurança da pasta publicada (roteiro para repetir antes de cada
  push): procurar CNPJ/CPF formatado (`\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}` e
  `\d{3}\.\d{3}\.\d{3}-\d{2}`) e os nomes reais conhecidos. Hoje só sobra o
  `00.000.000/0000-00` do placeholder do campo.
- Para ver a demo local: `php -S 127.0.0.1:8181 -t docs` (já existe a
  configuração `demo` no `.claude/launch.json`, que é local e não vai para o Git).

### Interface — modo escuro
- Botão sol/lua no header alterna o tema; escolha persiste em `localStorage` (`ut-theme`) e é
  aplicada antes da pintura (sem "flash"). CSS do tema escuro em `partials/styles.blade.php`
  (bloco "MODO ESCURO"), cobrindo shell, cards, tabelas e formulários. Sidebar já é verde nos dois
  modos; a tela de login permanece na cena "café".

## 4. Decisões técnicas importantes

- **Cálculos críticos sempre no servidor**: `valor_total` (model `FinanceiroCompra`) e
  `quantidade_lotes` (model `Classificacao`) são recalculados no evento `saving` do model,
  nunca aceitos do formulário. Não confiar em conta feita no navegador.
- **Fornecedor reaproveitado por CNPJ** via `firstOrCreate` (evita duplicar).
- **Listas centralizadas** em métodos estáticos dos models (armazéns saíram daqui e
  viraram cadastro — ver `Armazem::lista()`): `Compra::certificacoes()` /
  `Compra::certificacoes()`, `Classificacao::padroes()` (padrão final da classificação) e
  as listas do `Contrato` (certificados, embalagens, incoterms, portos, meses). Código curto
  => rótulo bonito; alimentam formulário, validação e exibição.
- **AuditLog** registra ações sensíveis (ex.: geração de link compartilhável).
- Auth/roles à mão para reduzir dependências de terceiros e facilitar auditoria.

## 5. Problemas já resolvidos (GOTCHAS — reler antes de mexer)

1. **Migrations de fábrica do Laravel conflitam com as do projeto.** O Laravel novo já traz
   `0001_01_01_000000_create_users_table.php` (cria `users`, `password_reset_tokens`,
   `sessions`). O projeto tem as suas próprias versões dessas tabelas. Solução aplicada:
   **apagar** a `0001_01_01_000000_create_users_table.php`, **mantendo** as de `cache` e
   `jobs` (o `.env` usa banco para cache e filas).
2. **Pluralização português × inglês no Eloquent.** Quando o model não declara a tabela, o
   Laravel "chuta" o nome em inglês. Os models `Fornecedor` (→ `fornecedores`) e
   `Classificacao` (→ `classificacoes`) precisam de `protected $table = '...'` explícito,
   senão o Laravel procura por `fornecedors`/`classificacaos`. `FinanceiroCompra` já tinha.
3. **Coluna ENUM `certificacao`** (e o fim do ENUM em `padrao_final`).
   **`classificacoes.padrao_final` NÃO é mais ENUM** (virou VARCHAR NULL em
   ago/2026, ver Módulo 1): acrescentar padrão é só editar
   `Classificacao::padroes()`. O que segue valendo para `certificacao`: Para adicionar uma opção de certificação é preciso mexer
   em **dois lugares**: o método `Compra::certificacoes()` (dropdown + validação) **e** a
   lista do ENUM na migration `..._create_compras_table.php`. Se só o model mudar, o MySQL
   trunca o valor. Já adicionamos o código `SEM_CERT` ("Sem certificação").
   **Em produção** (com dados reais) isso deve ser feito com uma migration de `ALTER`, não com
   `migrate:fresh`. **O mesmo vale para `classificacoes.padrao_final`**: para adicionar um padrão
   é preciso mexer em `Classificacao::padroes()` **e** ampliar o ENUM via migration de `ALTER`
   (ver `..._add_padroes_to_classificacoes.php`, que só roda no MySQL — no SQLite dos testes a
   coluna é texto). Já adicionados `GOOD_CUP_2R` e `RIO_MINAS`.
4. **"35 problemas" no VS Code** são falsos-positivos do Intelephense (ex.: *Undefined type
   'Illuminate\...\Model'*) quando ele não indexou a pasta `vendor/`. **Não afetam a execução**.
   Resolver com Ctrl+Shift+P → "Developer: Reload Window" e aguardar a indexação. Erros de
   *Undefined property* de atributos do Eloquent são inerentes e podem ser ignorados.
5. **Mensagens de validação apareciam como "validation.required" na tela.** O `.env` usa
   `APP_LOCALE=pt_BR` **e** `APP_FALLBACK_LOCALE=pt_BR`, mas não existia a pasta
   `lang/pt_BR/`. Sem o arquivo do locale — e sem fallback em inglês, já que o fallback
   também é pt_BR — o Laravel imprime a **chave crua** da tradução. Afetava **todos** os
   formulários do sistema. Corrigido criando `lang/pt_BR/validation.php` (mensagens +
   `attributes` com o rótulo de cada campo como aparece no formulário), `passwords.php`,
   `auth.php` e `pagination.php` (os botões do paginador saíam "pagination.previous").
   **Campo novo em formulário ⇒ acrescentar a etiqueta em `validation.attributes`**, senão
   a mensagem sai com o nome técnico da coluna.
   Guardado por `tests/Feature/MensagensValidacaoTest.php`, que checa a existência de cada
   regra traduzida e varre a tela renderizada procurando `validation.`.
6. **Mensagem de validação sem o sufixo da regra vale para TODAS as regras do campo.**
   Havia `'fornecedor_cnpj' => 'CNPJ inválido.'` no `StoreCompraRequest` e
   `'current_password' => 'A senha atual ... está incorreta.'` no `ChangePasswordRequest`:
   com o campo **em branco**, a tela dizia "CNPJ inválido"/"senha incorreta" em vez de pedir
   o preenchimento. A chave precisa ser `campo.regra`
   (`fornecedor_cnpj.required`, `current_password.current_password`).
7. **Nos testes, o flash de erros não sobrevive a uma requisição separada** (sessão `array`
   + `actingAs()` migrando a sessão). Para conferir o que o usuário vê depois de um POST
   inválido, use `->from(rota)->followingRedirects()->post(...)`, que renderiza o destino no
   mesmo ciclo — não faça `post()` e depois um `get()` esperando achar as mensagens.
8. **`@if` colado numa palavra não é compilado pelo Blade.** Para tirar o espaço
   antes do ponto final, `...como final@if (...) (contratado era ...)@endif.` foi
   escrito numa linha só — e o Blade **não reconheceu** o `@if` grudado em
   "final", só o `@endif`, que então fechou o `@if` de fora. Resultado: erro de
   sintaxe PHP ("unexpected token elseif") na tela inteira. Quando precisar de
   texto condicional sem quebra de linha, monte a string num `@php` e imprima
   com `{{ }}`.
9. **Na demo, nunca escreva "localhost" na mão num regex.** O `APP_URL` é
   `http://localhost:8000`: um padrão `https?://localhost/compras/...` passa
   batido por causa da porta, e o link sai meio-convertido
   (`compra.html/editar`). O gerador deriva a base de `url('/')` e tem duas
   asserções de guarda: nenhuma URL de rota sobrando e nenhum `href`/`action`
   com sufixo colado (atenção: `formaction` também casa com `action="`).
10. **O gerador da demo roda em SQLite e só conhece os ENUMs originais.**
   `GOOD_CUP_2R` e `RIO_MINAS` entram por migration de `ALTER` que só roda no
   MySQL (GOTCHA 3), então no SQLite o CHECK da coluna recusa esses códigos —
   o cenário da demo usa apenas `FINE_CUP`/`GOOD_CUP`.
11. **`padrao_final` ficou AMBÍGUO no SQL do Estoque.** A coluna passou a existir
   nas duas tabelas do join (`compras` e `classificacoes`) quando a compra
   ganhou a qualidade negociada — e o `select`/`group by` sem prefixo derrubou a
   tela inteira com "ambiguous column name" (SQLite) em 15 testes de uma vez.
   Vale a da **classificação** (a conferência), qualificada como
   `classificacoes.padrao_final as padrao_final`. Lição: coluna com o mesmo nome
   em duas tabelas de um join precisa de prefixo em TODO lugar, inclusive
   `groupBy`/`orderBy`.
12. **`@dataProvider` em docblock não roda mais.** No PHPUnit deste projeto o
   provider só é reconhecido pelo **atributo** `#[DataProvider('metodo')]`
   (`use PHPUnit\Framework\Attributes\DataProvider;`). Com a anotação antiga o
   teste falha com "Too few arguments" — parece erro do teste, mas é o provider
   que nunca foi chamado.
13. **Erro de validação de SOMA não deve morar num campo.** A soma das
   porcentagens era reportada em `peneira_1718_pct` (a "primeira" faixa) — e ao
   inserir SCS 12 UP no topo, a mensagem mudou de campo sozinha, quebrando
   testes e confundindo a tela. Agora tem chave própria (`soma_pct`,
   `soma_sacas`) exibida acima da tabela.
14. **Página do relatório renderizava tudo duas vezes.** O arquivo
   `dashboard/compras.blade.php` tinha o conteúdo inteiro **colado duas vezes dentro do próprio
   arquivo** (resíduo de uma edição anterior), então o form/tabela apareciam repetidos na tela.
   Corrigido reescrevendo o arquivo do zero. Fica como lembrete: depois de editar uma view,
   reabrir o arquivo (ou `git diff`) para confirmar que não sobrou conteúdo duplicado.

15. **Snapshot curto demais: `fixacoes.corretora` era VARCHAR(30).** As
   colunas `corretora`/`broker_cliente` foram dimensionadas na época em que
   guardavam CÓDIGOS (`STONEX`, `ICAP`). Quando as corretoras viraram
   cadastro, a fixação passou a gravar o **nome** como snapshot — e
   `corretoras.nome` aceita 80. Resultado: escolher "ICAP Corporates LLC
   (Hedgepoint)" (32 caracteres) ou "Marex Financial Limited AGS Coffee"
   (34) derrubava a Tela NY com *"Data too long for column 'corretora'"* no
   INSERT. Só a StoneX East Coast (17) cabia — e era justamente a usada nos
   testes. Corrigido em `..._alarga_corretora_nas_fixacoes` (as duas colunas
   passaram a 80, o tamanho da origem). **Regra**: coluna de snapshot nunca
   pode ser menor do que a coluna que ela copia.
16. **O SQLite dos testes não guarda o tamanho de VARCHAR.** O driver do
   Laravel emite `"corretora" varchar` — **sem limite** — e o SQLite aceita
   qualquer string. Por isso o GOTCHA 15 passou despercebido: o teste de
   fixação gravava um nome de 32 caracteres e passava, enquanto o MySQL
   recusava. E não adianta conferir pelo schema (`Schema::getColumns`), que
   no SQLite devolve só `varchar`, sem o número. Conclusão prática: **erro de
   tamanho de coluna não é pegável pela suíte** — quando mexer no tamanho de
   um VARCHAR, confira no MySQL de verdade.

17. **No `mailto:`, destinatário se separa com PONTO E VÍRGULA.** A RFC 6068
   manda vírgula, e foi assim que a primeira versão saiu — o Outlook então
   colou os 9 endereços da StoneX num destinatário só (a lista inteira como UM contato), e o e-mail não ia. O Outlook do
   Windows só aceita vírgula quando a opção *"é possível usar vírgulas para
   separar vários destinatários"* está ligada, e ela vem **desligada**; ponto
   e vírgula ele sempre entende. Como o link existe para abrir o Outlook,
   vale o que o Outlook entende (`InstrucaoAA::SEPARADOR_DESTINATARIOS`). A
   macro da planilha não sofria disso porque adicionava um a um via
   `Recipients.Add`. **Nenhum teste pega isso**: a URL fica igualmente
   válida das duas formas — quem reprova é o cliente de e-mail.

18. **O `LOWER()` do SQLite só converte ASCII.** `WHERE LOWER(nome) = ?` com
   "TRANSAÇUCENA" não casava com "transaçucena" nos testes (no MySQL
   casaria). Para comparar nome com acento sem diferenciar maiúsculas,
   compare em PHP com `mb_strtolower` — ver `Transportadora::porNome()`.
19. **A planilha de posição tinha sacas digitadas à mão que não batiam com os
   quilos** (UT 6025 e 6026: 21.600 kg com 720 sacas; o certo são 360). O
   total da planilha estava 720 sacas acima. No sistema as sacas são sempre
   calculadas dos quilos — mas vale a regra para qualquer carga futura:
   **conferir a soma de sacas contra kg ÷ 60 antes de confiar no total**.

20. **O paginador padrão do Laravel exige Tailwind.** As cinco listas
   paginadas (`->links()` em contratos, compras, clientes, qualidades e
   usuários) usavam o template padrão, cujas setas são SVG com classes
   Tailwind (`w-5 h-5`). Sem aquele CSS — o projeto não tem build step — o
   SVG cresce até ocupar a tela. **Ficou escondido até a carga de nov/2026**,
   quando "Contratos gerados" passou de 20 linhas pela primeira vez: nenhuma
   lista tinha paginado em uso real, e nenhum teste criava registros
   suficientes. Corrigido com `partials/paginacao.blade.php` (texto e classes
   do sistema), registrado como padrão em `AppServiceProvider`
   (`Paginator::defaultView`). Guardado por `PaginacaoTest`.
21. **`display:flex` direto num `<td>` quebra a linha da tabela.** A célula
   deixa de ser célula de tabela; em "Contratos gerados" os três botões
   empilhavam e cada linha tinha 91px. O flex vai num `<div>` interno
   (corrigido também na tabela de entregas de `compras/show`).
22. **Filho de grid não encolhe abaixo do conteúdo.** No celular, uma tabela
   larga dentro de `.admin-grid` esticava a coluna para 710px numa tela de
   375 e a PÁGINA inteira rolava de lado (Tela NY). `min-width: 0` nos
   filhos do grid devolve a rolagem para o `.table-wrap`. No mesmo pente, o
   cabeçalho (`.appbar`) passou a quebrar em duas linhas até 780px e esconde
   nome/perfil até 560px — eram eles que empurravam "Sair" para fora.
23. **Método e scope com o mesmo nome não convivem.** `aguardandoBaixa()`
   (instância) + `scopeAguardandoBaixa` → "Non-static method cannot be
   called statically" ao chamar `Embarque::aguardandoBaixa()`. O scope
   virou `faturadosSemBaixa`.
24. **Comentário dentro de `<script>` é texto da página para os testes.**
   Um `/** ... a página mais recente ... */` no JS do chat (que agora está
   em toda tela) fez um `assertDontSee('a mais')` de outra tela falhar.
   Comentário que vai para o navegador conta como conteúdo.
25. **Varredura de segurança de 17/11/2026** — ver `SECURITY.md` para o
   checklist; o que mudou: o disco `local` deixou de ser servido por HTTP
   (`'serve' => false`); `composer audit` voltou a acusar pacotes com falha
   conhecida (Laravel, Symfony, league/commonmark) — **falta
   `composer update`**; não há `Content-Security-Policy` nem `trustHosts`.
26. **O PROGRESSO é público: regra sim, número não.** Volumes de estoque e
   de venda, preços, nomes de cliente/fornecedor e e-mails de pessoas NÃO
   entram aqui nem em teste, comentário ou placeholder — vão para
   `storage/app/private/REGISTRO-DE-CARGAS.md`. Em 17/11/2026 os testes
   trocaram os nomes reais por fictícios (ALFA, BETA, GAMA, TRANSNORTE…) e o
   telefone/e-mail do cabeçalho dos PDFs foram para o `.env`
   (`EMPRESA_TELEFONE`, `EMPRESA_EMAIL`, `config/empresa.php`). **O que já
   foi enviado ao GitHub continua no histórico** — tirar de lá exige
   reescrever o histórico ou tornar o repositório privado.
- **Busca em "Contratos gerados"** (nov/2026): UT, comprador ou ref. do
  comprador, com `withQueryString()` para a paginação não perder o termo.

## 6. O que falta / próximos passos

- ~~**E-mail de produção**: configurar `MAIL_*` e criar uma `Notification` para enviar a senha
  temporária ao criar usuário.~~ **FEITO**: Notification `App\Notifications\CredenciaisDeAcesso`
  (e-mail em PT com e-mail, senha temporária e botão de acesso) disparada no `UserController::store`
  e `::resetPassword`. Robustez: o envio é **síncrono** (sem fila/worker) e envolto em try/catch —
  se o e-mail falhar, a ação NÃO é revertida e a senha aparece na tela como fallback; em ambiente
  sem e-mail real (`MAIL_MAILER=log`) a senha também é mostrada. Senha temporária agora é
  **alfanumérica** (`Str::password(16, symbols: false)`) para não quebrar a formatação do e-mail.
  Rodapé do template traduzido em `lang/pt_BR.json`. `.env.example` traz exemplo de SMTP de
  produção. Testado com `Notification::fake()` (`tests/Feature/CredenciaisEmailTest.php`).
  **Falta em produção**: apenas preencher os `MAIL_*` reais no `.env` do servidor.
- ~~**Testes automatizados** para os cálculos críticos (lotes, valor total, soma de peneiras).~~
  **FEITO**: `tests/Feature/CalculosCriticosTest.php` (lotes = soma das sacas ÷ 283,49 e
  valor_total = valor_saca × volume, ambos recalculados no servidor e **ignorando** valor
  forjado no atributo) e `tests/Feature/ClassificacaoHttpTest.php` (via rota: porcentagens
  devem fechar 100%, sacas não podem passar do volume, caminho feliz salva + calcula lotes,
  e usuário sem permissão recebe 403). Também corrigido o `ExampleTest` de scaffold (que
  esperava `GET /` = 200; a raiz não existe de propósito → agora testa `/` 404 e `/login` 200).
  Rodar com `php artisan test` (SQLite em memória; 11 testes verdes).
- ~~**Deploy**: revisar o checklist do `SECURITY.md`.~~ **REVISADO** (07/ago/2026): todas as
  afirmações do SECURITY.md conferidas contra o código atual — auth com throttle duplo +
  sessão regenerada + sem enumeração de usuário; 3 middlewares de autorização (role 403,
  conta.ativa, senha.pendente); `SecurityHeaders` global; `selectRaw` sem dado do usuário;
  zero `{!! !!}` nas views. `composer audit` **limpo** (nenhuma vulnerabilidade). Hardening
  aplicado: a descrição do perfil na tela de usuários passou a montar o texto com `textContent`
  (nunca `innerHTML`). SECURITY.md atualizado (credenciais por e-mail; nota de CSP inclui o
  `<script>` inline). **Só falta no servidor de produção** (não dá para fazer daqui): preencher
  o `.env` (APP_ENV=production, APP_DEBUG=false, APP_KEY, SESSION_SECURE_COOKIE=true, MAIL_*),
  redirect HTTP→HTTPS no servidor web, backups do banco e `composer install --no-dev`.
- ~~**Layout**: a tabela de "Compras lançadas" está ficando larga em telas menores.~~ **FEITO**:
  em telas ≤720px a tabela vira **cards empilhados** (cada linha = um card com "rótulo: valor"),
  sem scroll horizontal. Implementado com a classe `data--cards` + `data-label` em cada `<td>`
  (ver `partials/styles.blade.php`, seção "Tabela em cards"). No desktop continua tabela normal.
  Padrão reaproveitável em outras tabelas largas: basta a classe `data--cards` e `data-label`.
- **Opcional**: acrescentar colunas de peneira na quebra por certificação, se fizer sentido.
- ~~**Demo do GitHub Pages usável pelo cliente**~~ **FEITO (12/ago/2026)** para o módulo de
  compras (ver "Demo pública" na seção 3). **Contratos, Tela NY e cadastros seguem como
  demonstração fixa** — se for para valer também neles, o caminho é o mesmo
  (`docs/demo-compras.js` como modelo), mas é trabalho de outra sessão.
- **Usuário de demonstração** (12/ago/2026): uma conta `demo.…@` do domínio da empresa, perfil **admin**,
  criado direto no banco com senha aleatória e **sem** troca obrigatória no primeiro acesso
  (forçar a troca travaria justamente quem recebe a conta). A senha foi entregue no chat e
  não é recuperável — o banco guarda só o hash; para trocar, use "Resetar senha" na tela de
  usuários.
- **Cotações do robusta (Londres)**: Yahoo Finance não cobre — encontrar outra fonte
  (Barchart, Investing, ou dados oficiais ICE, que são pagos) e trocar os símbolos em
  `MercadoCafe::ROBUSTA`.
- ~~**Demo (GitHub Pages)**: criar as páginas estáticas da Tela NY e de Cotações.~~
  **FEITO**: `docs/tela-ny.html` (posição por tela + formulário interativo com fixação em
  grupo funcionando em JS) e `docs/cotacoes.html` (dados de exemplo reais de 10/ago);
  grupo "Mercado" adicionado ao menu de todas as páginas da demo. Gerado por script
  Python a partir de `contratos.html` (o CSS novo é copiado do bloco "Mercado" de
  `styles.blade.php` automaticamente).
- ~~**Demo desatualizada**: mostrava o modelo antigo de compras, sem entregas nem
  liquidação.~~ **FEITO (11/ago/2026)**: a demo passou a ser **gerada das views**
  (ver "Demo pública (GitHub Pages)" na seção 3). Entraram as telas de compra com
  entregas, divergência/liquidação, reabertura e o formulário de nova compra; o
  Estoque saiu do mesmo cenário, então os números fecham entre as telas. Ainda
  falta trocar os **nomes reais** que sobraram nas páginas mantidas à mão.
- **Ideias da planilha de posição da mesa** (analisada em 10/ago/2026 —
  `Z:\1-1_PLANILHAS NOVO SERVIDOR\3-2026-POSIÇAO\...\01-AGOSTO_10.08.2026.xlsx`), em
  ordem de aderência ao sistema:
  1. **Livro de hedge** (aba "POSIÇAO HEDGE"): operações de futuros LONG/SHORT por
     corretora e tela (nível, lotes, sacas, nº operação, vencimento) com **NET por tela**
     e **MtM** contra o mercado (o /api/market já dá o preço). Seria um módulo irmão da
     Tela NY. Obs.: lá aparecem corretoras a mais (Bradesco, Itaú BBA, BV, Marex) — se o
     módulo sair, a lista de corretoras precisa separar "corretoras de bolsa" × "bancos".
  2. **Logística de embarque nos contratos** (aba "VENDAS-FOB"): navio, booking, destino,
     EDT, armazém, mês de faturamento, status (A FATURAR/FATURADO) — campos que hoje só
     existem na planilha.
  3. **Long & Short / estoque** (abas RESUMO, ESTOQUE, SAAG, QUALITE): posição
     compras+estoque × vendas por mês e por "standard" (17/18 FC/GC, 14/16 FC/GC,
     grinders). Exigiria ligar classificação (peneiras) a saídas por contrato.

## 7. Mapa dos arquivos-chave

- **Models** (`app/Models/`): `Compra`, `Classificacao`, `Fornecedor`, `FinanceiroCompra`,
  `User`, `Role`, `AuditLog`.
- **Controllers** (`app/Http/Controllers/Compras/`): `CompraController`,
  `ClassificacaoController`, `FinanceiroController`, `DashboardController`.
- **Views de compras** (`resources/views/compras/`): `create`, `show`, `index`,
  `financeiro`, `classificacao`.
- **Views do relatório** (`resources/views/dashboard/`): `compras`, `compras-publico`,
  `_tabela-classificacao`.
- **Migrations** (`database/migrations/`): série `2026_01_01_0000xx_*` (roles, users,
  password/sessions, fornecedores, compras, classificacoes, financeiro_compras, audit_logs).
- **Seeders** (`database/seeders/`): `RoleSeeder`, `AdminUserSeeder`, `DatabaseSeeder`.
- **Config de segurança**: `bootstrap/app.php` (middlewares), `app/Providers/AppServiceProvider.php`
  (HTTPS em produção), `SECURITY.md` (detalhes das proteções e checklist de deploy).
- **Rotas**: `routes/web.php`.
- **Demo (GitHub Pages)**: páginas em `docs/`; gerador em `tests/Feature/GerarDemoTest.php`;
  motor interativo em `docs/demo-compras.js`.
- **Instruções de AA**: `app/Services/InstrucaoAA.php` (monta assunto, corpo e o
  `mailto:`), `App\Rules\ListaDeEmails` (formato das listas de e-mail),
  `Contrato::commodityDaTela()` e os campos de assinatura em `User`.
- **Listas centrais** (mexer aqui, não nas telas): `Classificacao::faixas()` (peneiras),
  `Classificacao::padroes()`, `Classificacao::tiposBebida()`, `Compra::tiposEntrada()`,
  `Compra::certificacoes()`, `Compra::logisticas()`. **Armazéns não são mais lista no
  código**: viraram cadastro (`Armazem`, tela em Cadastros → Armazéns); nas telas use
  `Armazem::lista()` (id => nome, memorizado por requisição).

## 8. Como retomar no Claude Code

1. Abrir a pasta `C:\laragon\www\union-trading` no Claude Code.
2. Pedir para ele ler este `PROGRESSO.md` primeiro.
3. Ligar o Laragon (MySQL) e rodar `php artisan serve`.
4. Seguir a partir da seção "O que falta / próximos passos".
