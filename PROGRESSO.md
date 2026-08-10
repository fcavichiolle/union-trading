# Union Trading — Estado do Projeto (PROGRESSO)

> Documento de contexto para retomar o desenvolvimento (inclusive no Claude Code).
> O código em si mora nos arquivos do projeto; este resumo guarda o "porquê" das
> decisões, os problemas já resolvidos e o que ainda falta.
>
> Última atualização: agosto/2026.

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
- E-mail admin atual: `traffic2@utrading.com.br`.

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
- **Início** (`dashboard/home.blade.php`): 4 cards de atalho (Nova compra, Compras lançadas,
  Relatório de classificação, Gestão de usuários), com visibilidade por perfil. Saudação usa o
  **nome do usuário logado**: `Bem-vindo, {{ $user->name }}.`
- **Gestão de usuários** (`admin/users/index.blade.php`): igual ao design — banner vermelho
  "Cadastro público desativado", **tabela de usuários** (avatar com iniciais, perfil em pill,
  último acesso, status Ativo/Suspenso; ações Editar/Resetar senha aparecem no hover e ficam
  fixas no mobile) e **formulário lateral "Adicionar usuário interno"** (nome, e-mail, perfil)
  que posta no `admin.users.store` já existente. A caixa "permissões do perfil" mostra a
  `descricao` real do Role selecionado via um JS mínimo (não inventa permissões). O
  `UserController::index` agora também passa `$roles` para a view.

### Módulo 1 — Compras e Classificação
- **Cadastro de compra**: UTS, mês/ano (rotulado "Mês/ano da entrega"), fornecedor + CNPJ
  validado, armazém, certificação, tipo de entrada (padrão "BICA"), volume em sacas.
- **Número do lote** (`compras.numero_lote`, coluna nova): preenchido **depois** do lançamento
  (não faz parte do formulário de criação — é adicionado quando o armazém/controle de estoque
  informa o número). Enquanto estiver em branco (`Compra::precisaDeNumeroLote()`), a compra
  **não é considerada definitivamente em estoque**: aparece um badge de alerta vermelho
  "⚠ Falta nº do lote" na coluna "Lote" de "Compras lançadas" e um banner de alerta no topo da
  tela da compra, com um formulário simples (`compras/{compra}/lote`, `PUT`) para preenchê-lo.
  Rota/controller (`CompraController::atualizarLote`) restrita a admin/compras, igual ao resto
  do módulo.
- **Classificação**: Fine Cup / Good Cup, distribuição nas peneiras 17/18, 14/16, mercado
  interno, grinders e **moka** (% e sacas). **Cálculo de lotes** (total de sacas ÷ 283,49) feito
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

### Módulo 2 — Contratos de exportação
- **Cadastros** (admin): **Clientes** (nome + endereço multilinha + **ref. padrão** opcional do
  comprador) e **Qualidades** (descrição do café). Rotas `admin.clientes.*` / `admin.qualidades.*`.
  Se o cliente tem ref. padrão, o formulário de contrato preenche o "Ref. Comprador" ao selecioná-lo
  (ex.: cliente **MIORI CF LLC** → `CONTRACT NO. 26-003 DD. 17.02.2026`).
- **Novo contrato** (`contratos.create`, perfis admin/compras): formulário em 4 blocos (igual ao
  app-modelo) com **preview ao vivo** de sacas/lotes/containers. Container rotulado **TEUS (20')** /
  **FEUS (40')**.
- **Cálculos (servidor, `Contrato::saving`)**: sacas = kg÷**60** (÷**59** quando a embalagem é
  "Jute Bags 59kg"); lotes = round(sacas ÷ 283,49 arábica | ÷ 166,66 conilon) — arredondamento
  normal (1,51→2); containers = ceil(kg ÷ capacidade), capacidade **20'=22.000 / 40'=25.000 kg**;
  peso/container = kg÷containers. **Arábica/Conilon é escolhido por contrato.**
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
- **Snapshot**: grava nome/endereço do cliente e descrição da qualidade na criação → editar o
  cadastro depois **não altera** contratos antigos.
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
  fixação criada/excluída. **Corretoras (nossas)**: lista fixa em `Fixacao::corretoras()` —
  StoneX East Coast, ICAP Corporates LLC (Hedgepoint) e Marex Financial Limited AGS Coffee.
  **Broker do cliente**: campo opcional da fixação (`fixacoes.broker_cliente`), dropdown com
  lista fixa em `Fixacao::brokersCliente()` (Stonex Miami, Adm Investor Services Inc,
  Macquarie USA, Stonex London, Sucden London, Macquarie futures broker LLC, Stonex East
  Coast, Marex London). Para adicionar/renomear em qualquer uma: editar o array.
- **Posição de fixações por tela** (card no topo da Tela NY): para cada tela, os lotes/
  sacas **a fixar** (contratos pendentes agrupados pelo `mes_fixacao` previsto; sacas =
  lotes restantes × divisor do tipo de café) e os lotes **fixados** naquela tela com o
  **level médio ponderado**. Formato espelhado da aba "Resumo FOB → VENDAS À FIXAR POR
  REFERÊNCIA" da planilha de posição da mesa. Contrato sem `mes_fixacao` entra como
  "Sem tela definida". Cálculo em `FixacaoController::posicaoPorTela()`.
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
- **Listas centralizadas** em métodos estáticos dos models: `Compra::armazens()` /
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
3. **Coluna ENUM `certificacao`.** Para adicionar uma opção de certificação é preciso mexer
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
5. **Página do relatório renderizava tudo duas vezes.** O arquivo
   `dashboard/compras.blade.php` tinha o conteúdo inteiro **colado duas vezes dentro do próprio
   arquivo** (resíduo de uma edição anterior), então o form/tabela apareciam repetidos na tela.
   Corrigido reescrevendo o arquivo do zero. Fica como lembrete: depois de editar uma view,
   reabrir o arquivo (ou `git diff`) para confirmar que não sobrou conteúdo duplicado.

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
- **Cotações do robusta (Londres)**: Yahoo Finance não cobre — encontrar outra fonte
  (Barchart, Investing, ou dados oficiais ICE, que são pagos) e trocar os símbolos em
  `MercadoCafe::ROBUSTA`.
- ~~**Demo (GitHub Pages)**: criar as páginas estáticas da Tela NY e de Cotações.~~
  **FEITO**: `docs/tela-ny.html` (posição por tela + formulário interativo com fixação em
  grupo funcionando em JS) e `docs/cotacoes.html` (dados de exemplo reais de 10/ago);
  grupo "Mercado" adicionado ao menu de todas as páginas da demo. Gerado por script
  Python a partir de `contratos.html` (o CSS novo é copiado do bloco "Mercado" de
  `styles.blade.php` automaticamente).
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

## 8. Como retomar no Claude Code

1. Abrir a pasta `C:\laragon\www\union-trading` no Claude Code.
2. Pedir para ele ler este `PROGRESSO.md` primeiro.
3. Ligar o Laragon (MySQL) e rodar `php artisan serve`.
4. Seguir a partir da seção "O que falta / próximos passos".
