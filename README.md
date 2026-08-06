# Union Trading — Sistema de Compras e Classificação de Café

Sistema web **B2B interno** da Union Trading para registrar **compras de café**, a
**classificação** dessas compras (distribuição em peneiras) e um **relatório gerencial**
somente leitura. Uso interno pelas equipes de compras, financeiro e diretoria — **sem
cadastro público**: todas as contas são criadas por um administrador.

> Para o contexto completo do projeto (decisões técnicas, histórico e próximos passos),
> veja [`PROGRESSO.md`](PROGRESSO.md). Para as proteções de segurança e o checklist de
> deploy, veja [`SECURITY.md`](SECURITY.md).

## Funcionalidades

- **Segurança e acesso**: login com proteção a força bruta, "esqueci minha senha", troca de
  senha obrigatória no primeiro acesso, perfis de acesso (admin / compras / financeiro /
  diretoria) e gestão de usuários — tudo escrito à mão, sem pacotes de terceiros de auth.
- **Compras e classificação**: cadastro de compra (UTS, fornecedor + CNPJ, armazém,
  certificação, volume), classificação nas peneiras (Fine/Good Cup, 17/18, 14/16, mercado
  interno, grinders) com cálculo de lotes no servidor, e módulo financeiro (valor da saca,
  total, corretor, comissão).
- **Relatório (dashboard)** somente leitura, com filtro por mês e link temporário assinado
  (7 dias) para compartilhar sem login.
- **Interface** com identidade visual própria (tela de login "café", sidebar verde, cards).

## Stack

- **PHP 8.3** + **Laravel** + **MySQL 8**
- Views em Blade, CSS em um único arquivo (`resources/views/partials/styles.blade.php`),
  **sem build step** (não usa Node/Vite/Tailwind compilado)
- Ambiente local: **Laragon** (Apache + MySQL) no Windows

## Como rodar localmente

Pré-requisitos: PHP 8.3, Composer e MySQL (ou Laragon, que já traz tudo).

```bash
# 1. Instalar dependências
composer install

# 2. Configurar o ambiente
cp .env.example .env
php artisan key:generate
# edite o .env com os dados do seu MySQL (DB_DATABASE=union_trading, etc.)
# e defina ADMIN_EMAIL para o e-mail do primeiro administrador

# 3. Criar o banco e o primeiro admin (mostra a senha temporária no terminal)
php artisan migrate --seed

# 4. Subir o servidor
php artisan serve
```

Acesse **http://localhost:8000/login** (não existe página na raiz `/`, é de propósito).
No primeiro login o sistema exige a troca da senha temporária.

## Segurança

- `.env` e logs **nunca** são versionados (ver `.gitignore`).
- Cálculos críticos (valor total, quantidade de lotes) são sempre refeitos no servidor.
- Detalhes das proteções e o checklist de produção estão em [`SECURITY.md`](SECURITY.md).

---

Projeto interno Union Trading · uso restrito.
