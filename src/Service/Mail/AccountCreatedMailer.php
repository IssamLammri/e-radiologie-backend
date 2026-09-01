<?php

namespace App\Service\Mail;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AccountCreatedMailer
{
    public function __construct(
        private readonly MailService $mailService,

        #[Autowire('%env(FRONTEND_URL)%')]
        private readonly string $frontendUrl,
    ) {
    }

    public function send(User $user, string $token): void
    {
        $resetUrl = sprintf(
            '%s/reset-password?token=%s',
            rtrim($this->frontendUrl, '/'),
            urlencode($token)
        );

        $this->mailService->send(
            to: $user->getEmail(),
            subject: 'Bienvenue sur e-Radiologie',
            template: 'emails/account_created.html.twig',
            context: [
                'user' => $user,
                'resetUrl' => $resetUrl,
            ]
        );
    }
}
