<?php

namespace App\Notifications;

use App\Models\GroupInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GroupInvitationReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly GroupInvitation $invitation) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $group = $this->invitation->group;
        $inviter = $this->invitation->inviter;

        return (new MailMessage)
            ->subject("{$inviter->first_name} t’invite dans « {$group->name} »")
            ->greeting('Salut 👋')
            ->line("{$inviter->name} t’invite à rejoindre le groupe « {$group->name} » sur Drop Picture.")
            ->line('Tu y recevras les photos et vidéos partagées **en qualité d’origine** — le fichier exact, sans aucune compression.')
            ->action('Rejoindre le groupe', $this->invitation->url())
            ->line('Pas encore de compte ? Le lien te le crée en un clic, et tu arrives directement dans le groupe.')
            ->salutation('L’équipe Drop Picture');
    }
}
