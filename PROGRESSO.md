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

### Módulo 1 — Compras e Classificação
- **Cadastro de compra**: UTS, mês/ano (rotulado "Mês/ano da entrega"), fornecedor + CNPJ
  validado, armazém, certificação, tipo de entrada (padrão "BICA"), volume em sacas.
- **Classificação**: Fine Cup / Good Cup, distribuição nas peneiras 17/18, 14/16, mercado
  interno e grinders (% e sacas). **Cálculo de lotes** (total de sacas ÷ 283,49) feito
  sempre no servidor.
- **Financeiro**: valor da saca, valor total (= saca × volume, calculado no servidor),
  corretor e comissão. Tem **preview do total em tempo real** no formulário (só visual;
  o valor oficial continua vindo do servidor ao salvar).
- **Relatório (dashboard)** somente leitura, com filtro por mês e **link temporário
  assinado (7 dias)** para compartilhar sem login. Contém duas tabelas:
  - Distribuição por **padrão** × peneira.
  - Distribuição por **certificação** (adicionada recentemente).
- **Tela "Compras lançadas"** com filtros por **intervalo de meses**, por **padrão** e
  busca por UTS/fornecedor; colunas de resumo (Volume, Mercado interno, Grinders) e
  coluna de **Certificação**, para ver tudo sem abrir compra por compra.

## 4. Decisões técnicas importantes

- **Cálculos críticos sempre no servidor**: `valor_total` (model `FinanceiroCompra`) e
  `quantidade_lotes` (model `Classificacao`) são recalculados no evento `saving` do model,
  nunca aceitos do formulário. Não confiar em conta feita no navegador.
- **Fornecedor reaproveitado por CNPJ** via `firstOrCreate` (evita duplicar).
- **Listas centralizadas** em métodos estáticos do model `Compra`: `armazens()` e
  `certificacoes()` (código curto => rótulo bonito). Essas listas alimentam formulário,
  validação e a exibição (tradução do código).
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
   `migrate:fresh`.
4. **"35 problemas" no VS Code** são falsos-positivos do Intelephense (ex.: *Undefined type
   'Illuminate\...\Model'*) quando ele não indexou a pasta `vendor/`. **Não afetam a execução**.
   Resolver com Ctrl+Shift+P → "Developer: Reload Window" e aguardar a indexação. Erros de
   *Undefined property* de atributos do Eloquent são inerentes e podem ser ignorados.

## 6. O que falta / próximos passos

- **E-mail de produção**: configurar `MAIL_*` e criar uma `Notification` para enviar a senha
  temporária ao criar usuário (hoje ela só aparece na tela para o admin repassar).
- **Testes automatizados** para os cálculos críticos (lotes, valor total, soma de peneiras).
- **Deploy**: revisar o checklist do `SECURITY.md` (HTTPS já é forçado em produção pelo
  `AppServiceProvider`).
- **Layout**: a tabela de "Compras lançadas" está ficando larga; avaliar esconder/reordenar
  colunas em telas menores.
- **Opcional**: acrescentar colunas de peneira na quebra por certificação, se fizer sentido.

## 7. Mapa dos arquivos-chave

- **Models** (`app/Models/`): `Compra`, `Classificacao`, `Fornecedor`, `FinanceiroCompra`,
  `User`, `Role`, `AuditLog`.
- **Controllers** (`app/Http/Controllers/Compras/`): `CompraController`,
  `ClassificacaoController`, `FinanceiroController`, `DashboardController`.
- **Views de compras** (`resources/views/compras/`): `create`, `show`, `index`,
  `financeiro`, `classificacao`.
- **Views do relatório** (`resources/views/dashboard/`): `compras`, `compras-publico`,
  `_tabela-classificacao`, `_tabela-certificacao`.
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
