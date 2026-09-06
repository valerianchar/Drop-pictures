<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Le lien de réinitialisation, en français et au ton de l'application.
 */
class ResetPassword extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Ton nouveau mot de passe Drop Picture')
            ->greeting('Salut '.$notifiable->first_name.' 👋')
            ->line('Tu as demandé à changer ton mot de passe. Clique ci-dessous pour en choisir un nouveau.')
            ->action('Choisir un nouveau mot de passe', $url)
            ->line("Ce lien est valable {$minutes} minutes.")
            ->line('Si tu n’as rien demandé, ignore simplement ce message : ton mot de passe reste inchangé.')
            ->salutation('L’équipe Drop Picture');
    }
}
