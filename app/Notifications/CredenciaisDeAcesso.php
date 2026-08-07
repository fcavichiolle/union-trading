<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail com as credenciais de acesso enviado ao usuário quando o admin
 * cria a conta ou redefine a senha. A senha aqui é a TEMPORÁRIA gerada
 * pelo sistema — o usuário é obrigado a trocá-la no primeiro login
 * (force_password_change), então trafega apenas uma vez.
 *
 * Propositalmente NÃO implementa ShouldQueue: o envio é síncrono para não
 * exigir um worker de fila rodando em produção. Se quiserem enfileirar
 * depois, basta implementar ShouldQueue e subir `php artisan queue:work`.
 */
class CredenciaisDeAcesso extends Notification
{
    public function __construct(
        public string $senhaTemporaria,
        public bool $isReset = false,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $assunto = $this->isReset ? 'Sua senha foi redefinida' : 'Seu acesso foi criado';

        return (new MailMessage)
            ->subject("Union Trading — {$assunto}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line($this->isReset
                ? 'Um administrador redefiniu a sua senha de acesso ao sistema Union Trading.'
                : 'Um administrador criou o seu acesso ao sistema Union Trading.')
            ->line("**E-mail:** {$notifiable->email}")
            ->line("**Senha temporária:** {$this->senhaTemporaria}")
            ->action('Acessar o sistema', route('login'))
            ->line('Por segurança, você precisará definir uma nova senha no primeiro acesso.')
            ->line('Se você não esperava este e-mail, avise o administrador do sistema.')
            ->salutation('Equipe Union Trading');
    }
}
