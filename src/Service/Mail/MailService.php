<?php

namespace App\Service\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

final class MailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,

        #[Autowire('%env(MAIL_FROM_EMAIL)%')]
        private readonly string $fromEmail,

        #[Autowire('%env(MAIL_FROM_NAME)%')]
        private readonly string $fromName,
    ) {
    }

    /**
     * Envoie un email HTML via Brevo.
     *
     * La version texte est automatiquement générée
     * depuis le HTML afin de fournir textContent à Brevo.
     */
    public function send(
        string $to,
        string $subject,
        string $template,
        array $context = [],
        ?string $attachmentPath = null,
        ?string $sender = null,
        ?string $senderName = null,
        ?string $cc = null,
        ?string $replyTo = null,
    ): void {
        try {
            $htmlContent = $this->twig->render(
                $template,
                $context
            );
        } catch (LoaderError|RuntimeError|SyntaxError $exception) {
            throw new \RuntimeException(
                'Erreur lors du rendu du template Twig : '
                . $exception->getMessage(),
                0,
                $exception
            );
        }

        /*
         * Brevo API attend également un textContent.
         *
         * On le génère automatiquement depuis le HTML :
         * aucun fichier .txt.twig supplémentaire n'est nécessaire.
         */
        $textContent = $this->htmlToText($htmlContent);

        $email = (new Email())
            ->from(
                new Address(
                    $sender ?? $this->fromEmail,
                    $senderName ?? $this->fromName
                )
            )
            ->to($to)
            ->subject($subject)
            ->html($htmlContent)
            ->text($textContent);

        if ($cc !== null) {
            $email->cc($cc);
        }

        if ($replyTo !== null) {
            $email->replyTo($replyTo);
        }

        if ($attachmentPath !== null) {
            if (!file_exists($attachmentPath)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Le fichier à attacher est introuvable : %s',
                        $attachmentPath
                    )
                );
            }

            $email->attachFromPath($attachmentPath);
        }

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $exception) {
            throw new \RuntimeException(
                'Erreur lors de l’envoi de l’email : '
                . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    /**
     * Génère automatiquement une version texte
     * à partir du template HTML.
     */
    private function htmlToText(string $html): string
    {
        // Conserve quelques retours à la ligne utiles.
        $text = preg_replace(
            [
                '/<br\s*\/?>/i',
                '/<\/p>/i',
                '/<\/div>/i',
                '/<\/h[1-6]>/i',
                '/<\/li>/i',
            ],
            [
                "\n",
                "\n\n",
                "\n",
                "\n\n",
                "\n",
            ],
            $html
        );

        $text = strip_tags($text);

        $text = html_entity_decode(
            $text,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        // Supprime les espaces inutiles.
        $text = preg_replace('/[ \t]+/', ' ', $text);

        // Évite d'avoir trop de lignes vides.
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }
}
