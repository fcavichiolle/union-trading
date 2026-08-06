<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Gestão de usuários — 100% restrita ao perfil admin (ver rotas).
 * NÃO existe rota pública de cadastro em nenhum lugar do sistema:
 * este é o ÚNICO lugar onde uma conta é criada.
 */
class UserController extends Controller
{
    public function index(): View
    {
        $users = User::with('role')->orderBy('name')->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        $roles = Role::orderBy('nome')->get();

        return view('admin.users.create', compact('roles'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $dados = $request->validated();

        // Se o admin não digitou uma senha, geramos uma temporária forte
        // aleatória. Em ambos os casos, force_password_change fica true:
        // o próximo login do usuário exige a troca antes de usar o sistema.
        $senhaTemporaria = $dados['password'] ?? Str::password(16);

        $user = User::create([
            'name' => $dados['name'],
            'email' => $dados['email'],
            'role_id' => $dados['role_id'],
            'password' => Hash::make($senhaTemporaria),
            'force_password_change' => true,
            'active' => true,
        ]);

        AuditLog::registrar('usuario_criado', "Usuário #{$user->id} ({$user->email}) criado por admin.", Auth::id());

        // TODO produção: em vez de mostrar a senha na tela, envie por
        // e-mail usando uma Notification (ver README, seção "Próximos passos").
        return redirect()
            ->route('admin.users.index')
            ->with('status', "Usuário criado. Senha temporária (mostrada uma única vez): {$senhaTemporaria}");
    }

    public function edit(User $user): View
    {
        $roles = Role::orderBy('nome')->get();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $dados = $request->validated();

        // Trava de segurança: impede que o admin desative/rebaixe a si
        // mesmo se ele for o único admin ativo restante — isso evitaria
        // que TODOS percam acesso ao painel administrativo.
        $vaiPerderAdmin = $user->id === Auth::id()
            && ($dados['active'] === false || Role::find($dados['role_id'])?->slug !== 'admin');

        if ($vaiPerderAdmin) {
            $outrosAdminsAtivos = User::whereHas('role', fn ($q) => $q->where('slug', 'admin'))
                ->where('active', true)
                ->where('id', '!=', $user->id)
                ->exists();

            if (! $outrosAdminsAtivos) {
                return back()->withErrors(['role_id' => 'Você é o único administrador ativo. Promova outro usuário a admin antes de alterar seu próprio acesso.']);
            }
        }

        $user->update([
            'name' => $dados['name'],
            'email' => $dados['email'],
            'role_id' => $dados['role_id'],
            'active' => $dados['active'],
        ]);

        AuditLog::registrar('usuario_atualizado', "Usuário #{$user->id} atualizado (perfil/status).", Auth::id());

        return redirect()->route('admin.users.index')->with('status', 'Usuário atualizado com sucesso.');
    }

    /** Gera nova senha temporária e força troca no próximo login. */
    public function resetPassword(User $user): RedirectResponse
    {
        $senhaTemporaria = Str::password(16);

        $user->forceFill([
            'password' => Hash::make($senhaTemporaria),
            'force_password_change' => true,
        ])->save();

        AuditLog::registrar('senha_resetada_por_admin', "Senha do usuário #{$user->id} resetada pelo admin.", Auth::id());

        return back()->with('status', "Nova senha temporária (mostrada uma única vez): {$senhaTemporaria}");
    }
}
