<?php

namespace App\Command;

use App\Service\Mail\MailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mail:test',
    description: 'Envoie un email de test via Brevo.',
)]
final class TestMailCommand extends Command
{
    public function __construct(
        private readonly MailService $mailService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'email',
            InputArgument::REQUIRED,
            'Adresse email destinataire'
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);

        $email = trim(
            (string) $input->getArgument('email')
        );

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error(sprintf(
                'Adresse email invalide : %s',
                $email
            ));

            return Command::FAILURE;
        }

        $io->info(sprintf(
            'Envoi d\'un email de test à %s...',
            $email
        ));

        try {
            $this->mailService->send(
                to: $email,
                subject: 'Test email - e-Radiologie',
                template: 'emails/test.html.twig',
                context: []
            );
        } catch (\Throwable $exception) {
            $io->error([
                'Échec de l\'envoi de l\'email.',
                $exception->getMessage(),
            ]);

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Email envoyé avec succès à %s.',
            $email
        ));

        return Command::SUCCESS;
    }
}
