# Union Trading — Sistema B2B (Módulo 0: Segurança + Módulo 1: Compras)

Este pacote contém o **código da aplicação** (models, controllers,
migrations, rotas, views) para os módulos de Autenticação e de
Compras/Classificação do sistema Union Trading, em **Laravel (PHP)**.

Ele **não é um projeto Laravel completo por si só** — faltam as
dependências do framework (pasta `vendor/`), que são baixadas pelo
Composer. O passo a passo abaixo mostra como criar o projeto Laravel
"vazio" e depois copiar estes arquivos por cima dele.

Stack escolhida: **PHP 8.4 + Laravel 13** (versão estável atual, ver
nota abaixo) + **MySQL 8**. Nenhuma dependência de terceiros além do
próprio Laravel é necessária (não usamos Breeze, Jetstream nem
pacotes de permissão prontos — autenticação e controle de acesso por
perfil foram escritos à mão, o que facilita a auditoria de segurança
do repositório: menos código de terceiro para confiar).

> Por que Laravel? Porque o framework já resolve, por padrão, boa
> parte dos pontos que costumam virar vulnerabilidade em sistemas
> feitos "na unha": proteção CSRF automática, escape de HTML no motor
> de views (Blade), hashing de senha, proteção contra SQL injection via
> ORM, etc. Ver `SECURITY.md` para o detalhamento de cada proteção.

---

## 1. O que instalar no computador

Você já tem o **VS Code**. Falta o seguinte:

### Windows — caminho mais simples: Laragon
1. Baixe e instale o **Laragon** (Full): https://laragon.org/download/
   Ele já vem com PHP, MySQL, Composer e Git juntos — um instalador só.
2. Abra o Laragon e clique em **Start** (sobe Apache/Nginx + MySQL).
3. Confirme as versões no terminal do Laragon (botão "Terminal"):
   ```
   php -v        # precisa ser 8.3 ou superior (recomendado 8.4)
   composer -V
   mysql --version
   git --version
   ```
   Se o PHP vier desatualizado, use o menu **Laragon > PHP > Version**
   para trocar para 8.4.

*Alternativa: XAMPP (https://www.apachefriends.org) + instalar o
Composer separadamente (https://getcomposer.org/download).*

### macOS — caminho mais simples: Laravel Herd
1. Baixe o **Herd**: https://herd.laravel.com (feito pela própria
   equipe do Laravel; já inclui PHP e Composer).
2. Instale o **MySQL** separadamente, por exemplo via Homebrew:
   ```
   brew install mysql
   brew services start mysql
   ```
3. Instale o **Git** se ainda não tiver: `brew install git`.

### Linux (Ubuntu/Debian)
```bash
sudo apt update
sudo apt install php8.4 php8.4-cli php8.4-mbstring php8.4-xml php8.4-mysql \
                  php8.4-curl php8.4-zip mysql-server git unzip
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```
(Se o repositório padrão da sua distro só tiver PHP mais antigo, adicione o
PPA `ppa:ondrej/php` antes do `apt install`.)

### Em qualquer sistema, também é bom ter:
- **Um cliente de banco de dados** (visualizar/editar tabelas com
  interface gráfica): **TablePlus** (https://tableplus.com, tem versão
  free) ou **DBeaver** (https://dbeaver.io, gratuito). O Laragon/XAMPP
  já vem com phpMyAdmin, que também resolve.
- **Extensões do VS Code** (Extensions → buscar e instalar):
  - `PHP Intelephense` (autocomplete/erros de PHP)
  - `Laravel Blade Snippets` (syntax highlight das views `.blade.php`)
  - `DotENV` (syntax highlight do `.env`)
  - Opcional: `Laravel Extra Intellisense`, `GitLens`

**Node.js/npm não é necessário** para este projeto: o CSS foi escrito
em um único arquivo incluído direto nas páginas (sem build step), então
não há Vite/Tailwind para compilar. Se no futuro vocês quiserem separar
CSS/JS em arquivos compilados, aí sim vale instalar o Node.js LTS.

---

## 2. Criar o projeto Laravel

No terminal (Laragon Terminal no Windows, ou terminal normal em
Mac/Linux), navegue até a pasta onde quer criar o projeto e rode:

```bash
composer create-project laravel/laravel union-trading "13.*"
cd union-trading
```

Isso baixa o Laravel 13 "vazio" (com `vendor/`, `composer.json`, um
`.env` de exemplo, etc.).

> **Nota sobre versão:** este código foi escrito para a estrutura do
> Laravel 12/13 (registro de middleware em `bootstrap/app.php`, sem
> `app/Http/Kernel.php`). Se por algum motivo o Composer instalar uma
> versão diferente, confira `composer.json` e ajuste conforme a
> documentação em https://laravel.com/docs.

---

## 3. Copiar os arquivos deste pacote para dentro do projeto

Extraia o `.zip` deste pacote e copie as pastas por cima da pasta
`union-trading` que o Composer acabou de criar, **sobrescrevendo**
quando perguntado. Isto é intencional para estes arquivos específicos
(eles substituem os padrões do Laravel):

| Arquivo deste pacote | O que acontece |
|---|---|
| `bootstrap/app.php` | **Substitui** o padrão — registra nossos middlewares de segurança |
| `app/Providers/AppServiceProvider.php` | **Substitui** o padrão — força HTTPS em produção |
| `routes/web.php` | **Substitui** o padrão — todas as rotas do sistema |
| `routes/console.php` | Substitui (fica só um comentário, sem uso por enquanto) |
| `database/seeders/DatabaseSeeder.php` | **Substitui** — chama os seeders de perfil/admin |
| Todo o resto (`app/Models`, `app/Http/Controllers`, `app/Http/Middleware`, `app/Http/Requests`, `app/Rules`, `database/migrations`, `database/seeders/RoleSeeder.php`, `database/seeders/AdminUserSeeder.php`, `resources/views`) | São **arquivos novos**, não existiam no projeto vazio |

No Windows/Mac, isso pode ser feito arrastando as pastas extraídas do
zip para dentro da pasta do projeto no Explorer/Finder e confirmando
"substituir". No terminal (Mac/Linux):

```bash
# a partir da pasta onde você extraiu o zip deste pacote
cp -R union-trading/app       /caminho/para/union-trading/
cp -R union-trading/bootstrap /caminho/para/union-trading/
cp -R union-trading/database  /caminho/para/union-trading/
cp -R union-trading/resources /caminho/para/union-trading/
cp -R union-trading/routes    /caminho/para/union-trading/
cp    union-trading/SECURITY.md /caminho/para/union-trading/
```

Depois, abra a pasta final no VS Code (`code .` dentro dela, ou
File → Open Folder).

---

## 4. Configurar o `.env`

1. Copie `.env.example` (deste pacote — já veio junto) para `.env` na
   raiz do projeto, **substituindo** o `.env` genérico que o Laravel
   criou.
2. Gere a chave da aplicação (usada para criptografar sessão/cookies):
   ```bash
   php artisan key:generate
   ```
3. Crie o banco de dados no MySQL. Pelo phpMyAdmin/TablePlus/DBeaver,
   ou via terminal:
   ```bash
   mysql -u root -p -e "CREATE DATABASE union_trading CHARACTER SET utf8mb4;"
   ```
4. Edite o `.env` e preencha `DB_USERNAME` / `DB_PASSWORD` com as
   credenciais do seu MySQL local (no Laragon, geralmente usuário
   `root` e senha em branco).
5. Defina `ADMIN_EMAIL` (e opcionalmente `ADMIN_PASSWORD`) no `.env` —
   é o e-mail do primeiro administrador do sistema, criado no próximo
   passo. Se deixar `ADMIN_PASSWORD` em branco, uma senha forte
   aleatória é gerada e mostrada no terminal.

---

## 5. Rodar as migrations e criar o primeiro administrador

```bash
php artisan migrate
php artisan db:seed
```

O segundo comando cria os perfis de acesso (admin, compras,
financeiro, diretoria) e o **primeiro usuário administrador** — como
não existe tela pública de cadastro, este é o único jeito de "entrar"
no sistema pela primeira vez. **Anote a senha temporária mostrada no
terminal**, você vai precisar dela no primeiro login (e será obrigado
a trocá-la na hora).

---

## 6. Rodar o projeto

```bash
php artisan serve
```

Acesse **http://localhost:8000/login** no navegador e entre com o
e-mail/senha do administrador criado no passo anterior. O sistema vai
pedir para trocar a senha antes de liberar o resto das telas.

A partir daí, use o menu lateral para:
- **Administração → Usuários**: criar as contas da equipe (compras,
  financeiro, diretoria), cada uma já com o perfil certo.
- **Compras & Classificação → Nova compra / Compras lançadas /
  Relatório**: os fluxos do Módulo 1 descritos no pedido.

---

## 7. Estrutura do que foi implementado

**Módulo 0 — Segurança e Controle de Acesso**
- Login com proteção contra força bruta e sessão renovada no login.
- "Esqueci minha senha" (envia link por e-mail) e "Alterar senha".
- Painel admin para criar usuários e definir perfil — **sem** cadastro
  público em nenhuma rota.
- Perfis (`roles`): admin, compras, financeiro, diretoria — cada rota
  do sistema é protegida por perfil no backend (`middleware('role:...')`).

**Módulo 1 — Compras e Classificação**
- Formulário de entrada da compra (UTS, mês/ano, fornecedor + CNPJ
  validado, armazém, certificação, tipo de entrada padrão "BICA",
  volume em sacas).
- Formulário de classificação pós-entrega (Fine Cup/Good Cup,
  distribuição nas peneiras 17/18, 14/16, mercado interno, grinders,
  com % e sacas), com **cálculo automático de lotes** (total de sacas
  ÷ 283,49) feito no servidor.
- Formulário financeiro (valor da saca, valor total calculado
  automaticamente, corretor e comissão).
- Dashboard somente leitura, agrupado por Padrão × Peneira, com % do
  total, filtro por mês e opção de gerar um **link temporário
  assinado** (7 dias) para compartilhar sem exigir login de quem
  recebe o link.

Veja `SECURITY.md` para o detalhamento de cada proteção implementada e
o checklist do que configurar antes de colocar em produção.

---

## 8. Próximos passos sugeridos (não incluídos neste pacote)

- **E-mail de produção:** hoje, ao criar um usuário, a senha temporária
  aparece na tela para o admin repassar manualmente. Para enviar por
  e-mail automaticamente, configure `MAIL_*` no `.env` e crie uma
  `Notification` (`php artisan make:notification NovoUsuarioCriado`).
- **Testes automatizados** (`php artisan make:test`) para os cálculos
  críticos (lotes, valor total, soma de peneiras).
- **Deploy:** qualquer host com PHP 8.3+/MySQL (ex: um VPS com Nginx +
  PHP-FPM, ou plataformas como Laravel Forge/Cloud) funciona. Sempre
  revisar o checklist de produção em `SECURITY.md` antes de publicar.
