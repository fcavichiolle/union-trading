<?php

use App\Http\Controllers\Admin\ClienteController;
use App\Http\Controllers\Admin\CorretoraController;
use App\Http\Controllers\Admin\QualidadeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Contratos\ContratoController;
use App\Http\Controllers\Contratos\FixacaoController;
use App\Http\Controllers\Mercado\MercadoController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Compras\ClassificacaoController;
use App\Http\Controllers\Compras\CompraController;
use App\Http\Controllers\Compras\EntregaController;
use App\Http\Controllers\Compras\DashboardController as ComprasDashboardController;
use App\Http\Controllers\Compras\FinanceiroController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas convidado (não autenticado)
|--------------------------------------------------------------------------
| Propositalmente NÃO existe nenhuma rota de "registrar/cadastrar-se".
| Contas só são criadas pelo admin em /admin/usuarios.
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:6,1'); // 6 tentativas/min por IP, além do rate-limit manual no controller

    Route::get('/esqueci-senha', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/esqueci-senha', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('/redefinir-senha/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/redefinir-senha', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');
});

/*
|--------------------------------------------------------------------------
| Relatório público (opcional) via link assinado e temporário
|--------------------------------------------------------------------------
| Middleware 'signed' recusa a requisição se a assinatura/expiração
| do link não baterem — não precisa de login, mas também não fica
| aberto para sempre nem é adivinhável.
*/
Route::get('/relatorio-compras/publico', [ComprasDashboardController::class, 'publico'])
    ->name('relatorio.publico')
    ->middleware('signed');

/*
|--------------------------------------------------------------------------
| Rotas autenticadas
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'conta.ativa'])->group(function () {

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Acessível mesmo com troca de senha pendente (senão o usuário
    // ficaria travado sem conseguir trocar a própria senha).
    Route::get('/trocar-senha', [ChangePasswordController::class, 'create'])->name('senha.trocar.form');
    Route::put('/trocar-senha', [ChangePasswordController::class, 'update'])->name('senha.trocar.update');

    // A partir daqui, se force_password_change=true o usuário é
    // redirecionado para /trocar-senha antes de ver qualquer outra tela.
    Route::middleware('senha.pendente')->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        /*
        |----------------------------------------------------------------
        | Módulo 0 — Painel Admin (gestão de usuários)
        |----------------------------------------------------------------
        */
        Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
            Route::get('/usuarios/novo', [UserController::class, 'create'])->name('users.create');
            Route::post('/usuarios', [UserController::class, 'store'])->name('users.store');
            Route::get('/usuarios/{user}/editar', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/usuarios/{user}', [UserController::class, 'update'])->name('users.update');
            Route::patch('/usuarios/{user}/resetar-senha', [UserController::class, 'resetPassword'])->name('users.reset-password');

            // Cadastros usados pelos contratos de exportação
            Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
            Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
            Route::get('/clientes/{cliente}/editar', [ClienteController::class, 'edit'])->name('clientes.edit');
            Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
            Route::delete('/clientes/{cliente}', [ClienteController::class, 'destroy'])->name('clientes.destroy');

            // Corretoras (nossas) e brokers dos clientes — dropdowns da Tela NY
            Route::get('/corretoras', [CorretoraController::class, 'index'])->name('corretoras.index');
            Route::post('/corretoras', [CorretoraController::class, 'store'])->name('corretoras.store');
            Route::put('/corretoras/{corretora}', [CorretoraController::class, 'update'])->name('corretoras.update');
            Route::delete('/corretoras/{corretora}', [CorretoraController::class, 'destroy'])->name('corretoras.destroy');

            Route::get('/qualidades', [QualidadeController::class, 'index'])->name('qualidades.index');
            Route::post('/qualidades', [QualidadeController::class, 'store'])->name('qualidades.store');
            Route::put('/qualidades/{qualidade}', [QualidadeController::class, 'update'])->name('qualidades.update');
            Route::delete('/qualidades/{qualidade}', [QualidadeController::class, 'destroy'])->name('qualidades.destroy');
        });

        /*
        |----------------------------------------------------------------
        | Módulo 2 — Contratos de exportação
        |----------------------------------------------------------------
        */
        Route::middleware('role:admin,compras')->prefix('contratos')->name('contratos.')->group(function () {
            Route::get('/', [ContratoController::class, 'index'])->name('index');
            Route::get('/novo', [ContratoController::class, 'create'])->name('create');
            Route::post('/', [ContratoController::class, 'store'])->name('store');
            Route::get('/{contrato}', [ContratoController::class, 'show'])->name('show');
            Route::get('/{contrato}/editar', [ContratoController::class, 'edit'])->name('edit');
            Route::put('/{contrato}', [ContratoController::class, 'update'])->name('update');
            // Cancelar mantém o registro (com motivo); excluir remove de vez
            // e é bloqueado quando já existem fixações (ver controller).
            Route::patch('/{contrato}/cancelar', [ContratoController::class, 'cancelar'])->name('cancelar');
            Route::patch('/{contrato}/reativar', [ContratoController::class, 'reativar'])->name('reativar');
            Route::delete('/{contrato}', [ContratoController::class, 'destroy'])->name('destroy');
            Route::get('/{contrato}/pdf', [ContratoController::class, 'pdf'])->name('pdf');
        });

        /*
        |----------------------------------------------------------------
        | Módulo 3 — Mercado (Tela NY + cotações)
        |----------------------------------------------------------------
        | A Tela NY (fixação de contratos) é restrita a quem opera
        | contratos; as cotações são leitura e abertas a todos os perfis.
        */
        Route::middleware('role:admin,compras')->group(function () {
            Route::get('/tela-ny', [FixacaoController::class, 'index'])->name('ny.index');
            Route::post('/tela-ny/fixacoes', [FixacaoController::class, 'store'])->name('ny.fixacoes.store');
            Route::delete('/tela-ny/fixacoes/{fixacao}', [FixacaoController::class, 'destroy'])->name('ny.fixacoes.destroy');
        });

        Route::middleware('role:admin,compras,financeiro,diretoria')->group(function () {
            Route::get('/mercado', [MercadoController::class, 'index'])->name('mercado.index');
            Route::get('/api/market', [MercadoController::class, 'api'])->name('mercado.api');
        });

        /*
        |----------------------------------------------------------------
        | Módulo 1 — Compras e Classificação
        |----------------------------------------------------------------
        */
        Route::middleware('role:admin,compras')->prefix('compras')->name('compras.')->group(function () {
            Route::get('/', [CompraController::class, 'index'])->name('index');
            Route::get('/novo', [CompraController::class, 'create'])->name('create');
            Route::post('/', [CompraController::class, 'store'])->name('store');
            // Busca a razão social pelo CNPJ (conveniência do formulário).
            Route::get('/cnpj/{cnpj}', [CompraController::class, 'consultarCnpj'])->name('cnpj');
            Route::get('/{compra}', [CompraController::class, 'show'])->name('show');
            Route::get('/{compra}/editar', [CompraController::class, 'edit'])->name('edit');
            Route::put('/{compra}', [CompraController::class, 'update'])->name('update');

            // Liquidar: aceita o volume entregue como final e cala os avisos
            // de diferença (ver ContratoController… CompraController::liquidar).
            Route::patch('/{compra}/liquidar', [CompraController::class, 'liquidar'])->name('liquidar');
            Route::patch('/{compra}/reabrir', [CompraController::class, 'reabrir'])->name('reabrir');

            // Entregas: o que realmente entrou no armazém (funcionário 3).
            Route::post('/{compra}/entregas', [EntregaController::class, 'store'])->name('entregas.store');
            Route::put('/{compra}/entregas/{entrega}', [EntregaController::class, 'update'])->name('entregas.update');
            Route::delete('/{compra}/entregas/{entrega}', [EntregaController::class, 'destroy'])->name('entregas.destroy');

            Route::get('/{compra}/classificacao', [ClassificacaoController::class, 'edit'])->name('classificacao.edit');
            Route::put('/{compra}/classificacao', [ClassificacaoController::class, 'update'])->name('classificacao.update');

            Route::get('/{compra}/financeiro', [FinanceiroController::class, 'edit'])->name('financeiro.edit');
            Route::put('/{compra}/financeiro', [FinanceiroController::class, 'update'])->name('financeiro.update');
        });

        // Dashboard/relatório (leitura), liberado também para diretoria.
        Route::middleware('role:admin,compras,diretoria')->group(function () {
            Route::get('/relatorio-compras', [ComprasDashboardController::class, 'index'])->name('relatorio.index');
            Route::post('/relatorio-compras/link', [ComprasDashboardController::class, 'linkTemporario'])->name('relatorio.link');
        });
    });
});
