# Deploy da demo (link permanente)

Guia para publicar o Union Trading numa hospedagem, de forma que o cliente acesse por um
link fixo. O projeto é **PHP 8.3 + Laravel + MySQL**.

> **Regra de ouro:** nunca comite o `.env` nem chaves/senhas. As variáveis abaixo são
> definidas no painel da hospedagem, não no repositório.

## Variáveis de ambiente (produção)

| Variável | Valor | Observação |
|---|---|---|
| `APP_NAME` | `Union Trading` | |
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | **nunca** `true` em produção |
| `APP_KEY` | *(gerar — veja abaixo)* | obrigatório |
| `APP_URL` | `https://SEU-DOMINIO` | o domínio que a hospedagem fornecer |
| `APP_LOCALE` | `pt_BR` | |
| `APP_TIMEZONE` | `America/Sao_Paulo` | |
| `DB_CONNECTION` | `mysql` | |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | *(do banco da hospedagem)* | a plataforma costuma preencher ao criar o MySQL |
| `SESSION_DRIVER` | `database` | |
| `SESSION_SECURE_COOKIE` | `true` | está em HTTPS |
| `CACHE_STORE` | `database` | |
| `QUEUE_CONNECTION` | `database` | |
| `MAIL_MAILER` | `log` | demo: e-mails vão para o log (não precisa SMTP) |
| `ADMIN_EMAIL` | `admin@utrading.com.br` | e-mail do admin da demo |
| `ADMIN_PASSWORD` | *(defina uma senha forte)* | usada só no seeder p/ criar o 1º admin |

**Gerar o `APP_KEY`** (rode localmente e copie o valor para a variável `APP_KEY` do painel):

```bash
php artisan key:generate --show
```

## Comandos de deploy (rodar no servidor a cada publicação)

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force        # cria as tabelas (inclui sessions/cache/jobs)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Uma única vez**, para criar o administrador da demo:

```bash
php artisan db:seed --force        # cria os perfis e o admin (usa ADMIN_EMAIL/ADMIN_PASSWORD)
```

O login da demo será **ADMIN_EMAIL + ADMIN_PASSWORD**. No primeiro acesso o sistema pede
para trocar a senha (é a proteção de primeiro login) — o cliente define a senha dele.

---

## Opção A — Laravel Cloud (recomendado)

1. Acesse **cloud.laravel.com** e entre com sua conta GitHub.
2. **New Application** → conecte o repositório `fcavichiolle/union-trading` (branch `master`).
3. **Add Database** → MySQL. A Laravel Cloud preenche as variáveis `DB_*` automaticamente.
4. Em **Environment**, defina as variáveis da tabela acima (APP_ENV, APP_DEBUG, APP_URL,
   APP_KEY, ADMIN_EMAIL, ADMIN_PASSWORD, SESSION_SECURE_COOKIE…).
5. Em **Deploy Command**, use: `composer install --no-dev --optimize-autoloader && php artisan migrate --force`.
6. Faça o **Deploy**. Depois, rode o seed uma vez pelo terminal/commands da plataforma:
   `php artisan db:seed --force`.
7. Abra a URL gerada → tela de login. Entregue ao cliente o link + ADMIN_EMAIL/senha.

## Opção B — Railway

1. Em **railway.app**, entre com o GitHub e **New Project → Deploy from GitHub repo** →
   `union-trading`.
2. **+ New → Database → MySQL**. Nas variáveis do serviço do app, referencie as do MySQL
   (`DB_HOST=${{MySQL.MYSQLHOST}}`, etc.) ou copie os valores.
3. Defina as demais variáveis da tabela acima.
4. Em **Settings → Deploy**, defina o start/release para rodar `php artisan migrate --force`
   (Railway usa Nixpacks e detecta o Laravel automaticamente).
5. Rode `php artisan db:seed --force` uma vez pelo shell do serviço.

## Opção C — Já tenho hospedagem (cPanel / VPS)

1. No servidor: `git clone https://github.com/fcavichiolle/union-trading.git` (ou `git pull`).
2. `composer install --no-dev --optimize-autoloader`
3. Crie o `.env` (copie de `.env.example`), preencha DB_* e as variáveis acima, e rode
   `php artisan key:generate`.
4. `php artisan migrate --seed --force`
5. Aponte o **DocumentRoot do domínio para a pasta `public/`** (importante no Laravel) e
   garanta HTTPS. `php artisan config:cache && route:cache && view:cache`.

---

Dúvidas na configuração das variáveis ou erro no deploy? Me chame que a gente resolve.
