<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Mail\PasswordResetMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'message' => 'Utilisateur non authentifié.',
            ], 401);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
        ]);
    }

    #[Route(
        '/forgot-password',
        name: 'api_auth_forgot_password',
        methods: ['POST']
    )]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        ResetPasswordHelperInterface $resetPasswordHelper,
        PasswordResetMailer $mailer,
    ): JsonResponse {
        $data = $request->toArray();

        $email = strtolower(
            trim(
                (string) ($data['email'] ?? '')
            )
        );

        if (!filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )) {
            return $this->json([
                'message' => 'Adresse email invalide.',
            ], 422);
        }

        $user = $userRepository->findOneBy([
            'email' => $email,
        ]);

        /*
         * Ne jamais indiquer au frontend si
         * l'adresse existe réellement.
         */
        if ($user instanceof User) {
            try {
                $resetToken =
                    $resetPasswordHelper
                        ->generateResetToken($user);

                $mailer->send(
                    $user,
                    $resetToken->getToken()
                );
            } catch (
            ResetPasswordExceptionInterface
            ) {
                /*
                 * Même réponse pour éviter
                 * l'énumération des utilisateurs.
                 */
            }
        }

        return $this->json([
            'message' =>
                'Si un compte correspond à cette adresse, '
                .'un email de réinitialisation a été envoyé.',
        ]);
    }

    #[Route(
        '/reset-password',
        name: 'api_auth_reset_password',
        methods: ['POST']
    )]
    public function resetPassword(
        Request $request,
        ResetPasswordHelperInterface $resetPasswordHelper,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $data = $request->toArray();

        $token = trim(
            (string) ($data['token'] ?? '')
        );

        $password =
            (string) ($data['password'] ?? '');

        $passwordConfirmation =
            (string) (
                $data['passwordConfirmation'] ?? ''
            );

        if ($token === '') {
            return $this->json([
                'message' =>
                    'Le token est obligatoire.',
            ], 422);
        }

        if ($password === '') {
            return $this->json([
                'message' =>
                    'Le mot de passe est obligatoire.',
            ], 422);
        }

        if (strlen($password) < 8) {
            return $this->json([
                'message' =>
                    'Le mot de passe doit contenir '
                    .'au moins 8 caractères.',
            ], 422);
        }

        if (
            $password !==
            $passwordConfirmation
        ) {
            return $this->json([
                'message' =>
                    'Les mots de passe '
                    .'ne correspondent pas.',
            ], 422);
        }

        try {
            $user =
                $resetPasswordHelper
                    ->validateTokenAndFetchUser(
                        $token
                    );
        } catch (
        ResetPasswordExceptionInterface
        ) {
            return $this->json([
                'message' =>
                    'Le lien de réinitialisation '
                    .'est invalide ou a expiré.',
            ], 400);
        }

        if (!$user instanceof User) {
            return $this->json([
                'message' =>
                    'Utilisateur invalide.',
            ], 400);
        }

        $hashedPassword =
            $passwordHasher->hashPassword(
                $user,
                $password
            );

        $user->setPassword(
            $hashedPassword
        );

        $entityManager->flush();

        /*
         * Rend le token inutilisable.
         */
        $resetPasswordHelper
            ->removeResetRequest(
                $token
            );

        return $this->json([
            'message' =>
                'Votre mot de passe a été '
                .'réinitialisé avec succès.',
        ]);
    }
}
