<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed-local', description: 'Crée les comptes de test du Docker local, sans modifier les comptes existants.')]
final class SeedLocalCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $hasher,
        #[Autowire('%kernel.environment%')] private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->environment !== 'dev' || ($_SERVER['APP_LOCAL_SETUP'] ?? $_ENV['APP_LOCAL_SETUP'] ?? getenv('APP_LOCAL_SETUP')) !== '1') {
            $output->writeln('<error>Cette commande est réservée à la configuration Docker locale.</error>');
            return Command::FAILURE;
        }

        foreach (['admin@e-radiologie.test' => ['ROLE_ADMIN'], 'client@e-radiologie.test' => ['ROLE_USER']] as $email => $roles) {
            if ($this->users->findOneBy(['email' => $email]) !== null) {
                continue;
            }

            $user = (new User())
                ->setEmail($email)
                ->setFirstName($roles === ['ROLE_ADMIN'] ? 'Admin' : 'Client')
                ->setLastName('Local')
                ->setRoles($roles);
            $user->setPassword($this->hasher->hashPassword($user, 'LocalTest123!'));
            $this->entityManager->persist($user);
        }

        $this->entityManager->flush();
        $output->writeln('<info>Comptes de test locaux prêts.</info>');
        return Command::SUCCESS;
    }
}
