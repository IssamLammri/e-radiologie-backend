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

        return $this->json($this->userData($user));
    }

    #[Route('/me', name: 'api_auth_update_me', methods: ['PATCH'])]
    public function updateMe(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Utilisateur non authentifié.'], 401);
        }

        $data = $request->toArray();
        $errors = [];

        $firstName = is_string($data['firstName'] ?? null) ? trim($data['firstName']) : null;
        $lastName = is_string($data['lastName'] ?? null) ? trim($data['lastName']) : null;
        $email = is_string($data['email'] ?? null) ? strtolower(trim($data['email'])) : null;

        if (array_key_exists('firstName', $data) && !is_string($data['firstName'])) {
            $errors['firstName'][] = 'Le prénom doit être une chaîne de caractères.';
        } elseif ($firstName !== null && $firstName === '') {
            $errors['firstName'][] = 'Le prénom ne peut pas être vide.';
        } elseif ($firstName !== null && strlen($firstName) > 255) {
            $errors['firstName'][] = 'Le prénom ne peut pas dépasser 255 caractères.';
        }
        if (array_key_exists('lastName', $data) && !is_string($data['lastName'])) {
            $errors['lastName'][] = 'Le nom doit être une chaîne de caractères.';
        } elseif ($lastName !== null && $lastName === '') {
            $errors['lastName'][] = 'Le nom ne peut pas être vide.';
        } elseif ($lastName !== null && strlen($lastName) > 255) {
            $errors['lastName'][] = 'Le nom ne peut pas dépasser 255 caractères.';
        }
        if (array_key_exists('email', $data) && !is_string($data['email'])) {
            $errors['email'][] = 'L’adresse email doit être une chaîne de caractères.';
        } elseif ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Adresse email invalide.';
        } elseif ($email !== null && strlen($email) > 180) {
            $errors['email'][] = 'L’adresse email ne peut pas dépasser 180 caractères.';
        } elseif (
            $email !== null
            && $userRepository->findOneByNormalizedEmail($email, $user->getId()) instanceof User
        ) {
            $errors['email'][] = 'Cette adresse email est déjà utilisée.';
        }

        if ($errors !== []) {
            return $this->validationError($errors);
        }

        $emailChanged = $email !== null && $email !== $user->getEmail();

        if ($firstName !== null) {
            $user->setFirstName($firstName);
        }
        if ($lastName !== null) {
            $user->setLastName($lastName);
        }
        if ($email !== null) {
            $user->setEmail($email);
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Profil mis à jour avec succès.',
            'user' => $this->userData($user),
            'requiresReauthentication' => $emailChanged,
        ]);
    }

    #[Route('/me/password', name: 'api_auth_update_my_password', methods: ['PATCH'])]
    public function updateMyPassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Utilisateur non authentifié.'], 401);
        }

        $data = $request->toArray();
        $currentPassword = is_string($data['currentPassword'] ?? null) ? $data['currentPassword'] : '';
        $newPassword = is_string($data['newPassword'] ?? null) ? $data['newPassword'] : '';
        $passwordConfirmation = is_string($data['passwordConfirmation'] ?? null)
            ? $data['passwordConfirmation']
            : '';
        $errors = [];

        if ($currentPassword === '') {
            $errors['currentPassword'][] = 'Le mot de passe actuel est obligatoire.';
        } elseif (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            $errors['currentPassword'][] = 'Le mot de passe actuel est incorrect.';
        }
        if (strlen($newPassword) < 8) {
            $errors['newPassword'][] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
        } elseif (strlen($newPassword) > 4096) {
            $errors['newPassword'][] = 'Le nouveau mot de passe est trop long.';
        }
        if ($newPassword !== $passwordConfirmation) {
            $errors['passwordConfirmation'][] = 'Les mots de passe ne correspondent pas.';
        }

        if ($errors !== []) {
            return $this->validationError($errors);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $entityManager->flush();

        return $this->json([
            'message' => 'Mot de passe modifié avec succès.',
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

    /**
     * @return array{id: int|null, email: string|null, firstName: string|null, lastName: string|null, roles: array}
     */
    private function userData(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
        ];
    }

    private function validationError(array $errors): JsonResponse
    {
        return $this->json([
            'message' => 'Les données envoyées sont invalides.',
            'errors' => $errors,
        ], 422);
    }
}
