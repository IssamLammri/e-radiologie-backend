<?php

namespace App\Service\Mail;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PasswordResetMailer
{
    public function __construct(
        private readonly MailService $mailService,

        #[Autowire('%env(FRONTEND_URL)%')]
        private readonly string $frontendUrl,
    ) {
    }

    public function send(
        User $user,
        string $token
    ): void {
        $resetUrl = sprintf(
            '%s/reset-password?token=%s',
            rtrim($this->frontendUrl, '/'),
            urlencode($token)
        );

        $this->mailService->send(
            to: $user->getEmail(),
            subject: 'Réinitialisation de votre mot de passe',
            template: 'emails/password_reset.html.twig',
            context: [
                'user' => $user,
                'resetUrl' => $resetUrl,
            ]
        );
    }
}
