# Segurança — Union Trading

Este documento existe para que qualquer pessoa revisando o repositório
consiga checar rapidamente **o que já está protegido** e **o que ainda
precisa ser configurado antes de ir para produção**. Nada aqui é
automático "por mágica do framework" sem entender o porquê — cada item
tem uma explicação curta.

## 1. Autenticação e sessão

- **Sem cadastro público.** Não existe rota `/register` em nenhum lugar
  do sistema. `routes/web.php` só tem `login`, `esqueci-senha` e
  `redefinir-senha` como rotas de convidado. Contas só nascem em
  `Admin\UserController::store`.
- **Senhas com hash bcrypt**, via cast `'password' => 'hashed'` no
  model `User` — nunca gravamos senha em texto puro, e o Laravel troca
  o hash automaticamente sempre que o campo recebe um valor novo.
- **Força bruta:** rota de login limitada por `throttle:6,1` (6
  tentativas/minuto por IP) **e** por um rate limit manual adicional em
  `AuthenticatedSessionController::store` (por e-mail + IP), então trocar
  de IP sozinho não contorna o limite por conta.
- **Sem enumeração de usuário:** login, "esqueci minha senha" e conta
  desativada sempre retornam mensagens genéricas — nunca dizemos "esse
  e-mail não existe" vs. "senha errada".
- **Fixação de sessão:** `$request->session()->regenerate()` é chamado
  logo após autenticar; `regenerateToken()` no logout.
- **Sessões em banco** (`SESSION_DRIVER=database`), o que permite
  revogar/auditar sessões ativas se necessário (tabela `sessions`).
- **Senha temporária obrigatoriamente trocada:** toda conta criada pelo
  admin nasce com `force_password_change = true`; o middleware
  `RedirectIfPasswordChangeRequired` bloqueia qualquer outra tela até a
  troca acontecer.
- **Conta desativada = fora na hora:** `EnsureUserIsActive` derruba a
  sessão de quem for desativado, mesmo que já estivesse logado.
- **Reset de senha:** token de e-mail é armazenado com hash (padrão do
  Laravel), expira em 60 minutos, e é de uso único.
- **Política de senha:** mínimo 12 caracteres, maiúsculas, minúsculas,
  números e símbolos (`Password::min(12)->mixedCase()->numbers()->symbols()`).
  No fluxo de "esqueci minha senha" e na troca de senha, também usamos
  `->uncompromised()`, que confere a senha contra a base pública de
  vazamentos (Have I Been Pwned) usando *k-anonymity* — **isso faz uma
  chamada HTTPS de saída do servidor**; se seu ambiente não tiver acesso
  à internet, remova `->uncompromised()` dessas regras.

## 2. Autorização (perfis de acesso)

- Toda rota sensível está atrás do middleware `role:...`
  (`EnsureUserHasRole`), checado **no servidor**, nunca só escondendo
  botão no menu.
- Cada `FormRequest` (`StoreUserRequest`, `StoreCompraRequest`, etc.)
  repete a checagem de perfil em `authorize()` — defesa em profundidade,
  caso uma rota seja movida de grupo de middleware por engano no futuro.
- `Admin\UserController::update` tem uma trava específica: impede que o
  único admin ativo se auto-rebaixe ou se autodesative.

## 3. Dados e banco

- **Mass assignment:** todo model define `$fillable` explicitamente.
  Nenhum model usa `$guarded = []`.
- **SQL injection:** todas as consultas usam Eloquent/Query Builder com
  *parameter binding*. O único `selectRaw` do projeto
  (`DashboardController::dadosRelatorio`) não interpola nenhum dado do
  usuário na string SQL — o filtro de mês entra via `whereYear`/`whereMonth`
  parametrizados.
- **Valores calculados nunca vêm do formulário:** `quantidade_lotes`
  (Classificacao) e `valor_total` (FinanceiroCompra) são recalculados no
  servidor (`booted()->saving`) a partir de outros campos — mesmo que
  alguém edite o HTML do formulário e envie um valor manipulado, ele é
  ignorado.
- **Regra de negócio validada no servidor:** soma das % das peneiras
  (~100%) e soma das sacas (≤ volume comprado) são conferidas em
  `StoreClassificacaoRequest::withValidator`, não só no JavaScript da tela.

## 4. Web (XSS / CSRF / clickjacking)

- **CSRF:** todo `<form>` usa `@csrf`; o middleware `web` do Laravel
  verifica o token em todo POST/PUT/PATCH/DELETE automaticamente.
- **XSS:** todas as views usam `{{ }}` (auto-escape do Blade). Não há
  nenhum `{!! !!}` no projeto.
- **Cabeçalhos de segurança** (`app/Http/Middleware/SecurityHeaders.php`,
  aplicado globalmente): `X-Frame-Options: DENY`,
  `X-Content-Type-Options: nosniff`, `Referrer-Policy`,
  `Permissions-Policy`, e `Strict-Transport-Security` quando a conexão
  já é HTTPS.

## 5. Auditoria

- Tabela `audit_logs` registra: login (sucesso/falha), logout, criação e
  edição de usuário, reset de senha por admin, redefinição/alteração de
  senha, acesso negado por perfil, e geração de link público do relatório
  — sempre com IP e usuário (quando aplicável).

## 6. Antes de ir para produção — checklist

- [ ] `APP_ENV=production` e `APP_DEBUG=false` no `.env` do servidor.
- [ ] `APP_KEY` gerado (`php artisan key:generate`) e mantido em segredo.
- [ ] HTTPS obrigatório (o `AppServiceProvider` já força `https://` nas
      URLs geradas quando `APP_ENV=production`, mas o **servidor web**
      também precisa redirecionar HTTP → HTTPS).
- [ ] `SESSION_SECURE_COOKIE=true` no `.env` de produção.
- [ ] Configurar um provedor de e-mail de verdade (`MAIL_*`) para o
      "esqueci minha senha" funcionar de fato.
- [ ] Trocar a senha do primeiro admin (gerada pelo `AdminUserSeeder`)
      assim que possível — o `force_password_change` já obriga isso no
      primeiro login.
- [ ] Backups automáticos do banco de dados.
- [ ] `composer install --no-dev --optimize-autoloader` no deploy (não
      instalar dependências de desenvolvimento em produção).
- [ ] Revisar periodicamente `composer audit` (verifica dependências
      com vulnerabilidades conhecidas).
- [ ] (Opcional, hardening extra) Definir uma Content-Security-Policy
      explícita — hoje o CSS é servido inline via
      `resources/views/partials/styles.blade.php`, o que é seguro para
      o uso atual, mas uma CSP restritiva exigiria mover para um arquivo
      externo com nonce.
