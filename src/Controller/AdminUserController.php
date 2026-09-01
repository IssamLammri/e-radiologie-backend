<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\Mail\AccountCreatedMailer;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/api/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class AdminUserController extends AbstractController
{
    #[Route('', name: 'api_admin_users_list', methods: ['GET'])]
    public function list(Request $request, UserRepository $userRepository): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        $search = trim((string) $request->query->get('search', ''));
        $result = $userRepository->findPaginated($page, $limit, $search);

        return $this->json([
            'items' => array_map($this->userData(...), $result['items']),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'totalItems' => $result['total'],
                'totalPages' => (int) ceil($result['total'] / $limit),
            ],
        ]);
    }

    #[Route('/{id<\d+>}', name: 'api_admin_users_show', methods: ['GET'])]
    public function show(User $user): JsonResponse
    {
        return $this->json($this->userData($user));
    }

    #[Route('', name: 'api_admin_users_create', methods: ['POST'])]
    public function create(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ResetPasswordHelperInterface $resetPasswordHelper,
        AccountCreatedMailer $accountCreatedMailer,
    ): JsonResponse {
        $data = $request->toArray();
        $validation = $this->validateProfileData($data, $userRepository, null, true);

        if ($validation['errors'] !== []) {
            return $this->validationError($validation['errors']);
        }

        $password = is_string($data['password'] ?? null) ? $data['password'] : '';
        $passwordConfirmation = is_string($data['passwordConfirmation'] ?? null)
            ? $data['passwordConfirmation']
            : '';
            
        if ($password !== '' || $passwordConfirmation !== '') {
            $errors = $this->validatePassword($password, $passwordConfirmation);
            if ($errors !== []) {
                return $this->validationError($errors);
            }
        } else {
            // Générer un mot de passe aléatoire très fort car l'utilisateur va le réinitialiser
            $password = bin2hex(random_bytes(32));
        }

        $user = (new User())
            ->setFirstName($validation['firstName'])
            ->setLastName($validation['lastName'])
            ->setEmail($validation['email'])
            ->setRoles($validation['roles']);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $entityManager->persist($user);
        $entityManager->flush();
        
        $resetToken = $resetPasswordHelper->generateResetToken($user);
        $accountCreatedMailer->send($user, $resetToken->getToken());

        return $this->json([
            'message' => 'Utilisateur créé avec succès.',
            'user' => $this->userData($user),
        ], 201);
    }

    #[Route('/{id<\d+>}', name: 'api_admin_users_update', methods: ['PATCH'])]
    public function update(
        User $user,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $data = $request->toArray();
        $validation = $this->validateProfileData($data, $userRepository, $user, false);

        if ($validation['errors'] !== []) {
            return $this->validationError($validation['errors']);
        }

        if ($validation['firstName'] !== null) {
            $user->setFirstName($validation['firstName']);
        }
        if ($validation['lastName'] !== null) {
            $user->setLastName($validation['lastName']);
        }
        if ($validation['email'] !== null) {
            $user->setEmail($validation['email']);
        }
        if ($validation['roles'] !== null) {
            $user->setRoles($validation['roles']);
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Utilisateur mis à jour avec succès.',
            'user' => $this->userData($user),
        ]);
    }

    #[Route('/{id<\d+>}/password', name: 'api_admin_users_update_password', methods: ['PATCH'])]
    public function updatePassword(
        User $user,
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $data = $request->toArray();
        $password = is_string($data['newPassword'] ?? null) ? $data['newPassword'] : '';
        $passwordConfirmation = is_string($data['passwordConfirmation'] ?? null)
            ? $data['passwordConfirmation']
            : '';
        $errors = $this->validatePassword($password, $passwordConfirmation, 'newPassword');

        if ($errors !== []) {
            return $this->validationError($errors);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $entityManager->flush();

        return $this->json([
            'message' => 'Mot de passe de l’utilisateur modifié avec succès.',
        ]);
    }

    #[Route('/{id<\d+>}', name: 'api_admin_users_delete', methods: ['DELETE'])]
    public function delete(User $user, EntityManagerInterface $entityManager): JsonResponse
    {
        $entityManager->remove($user);
        $entityManager->flush();

        return new JsonResponse(null, 204);
    }

    /**
     * @return array{firstName: ?string, lastName: ?string, email: ?string, roles: ?array, errors: array}
     */
    private function validateProfileData(
        array $data,
        UserRepository $userRepository,
        ?User $user,
        bool $allRequired,
    ): array {
        $errors = [];
        $firstName = is_string($data['firstName'] ?? null) ? trim($data['firstName']) : null;
        $lastName = is_string($data['lastName'] ?? null) ? trim($data['lastName']) : null;
        $email = is_string($data['email'] ?? null) ? strtolower(trim($data['email'])) : null;
        $roles = array_key_exists('roles', $data) ? $data['roles'] : null;

        if (array_key_exists('firstName', $data) && !is_string($data['firstName'])) {
            $errors['firstName'][] = 'Le prénom doit être une chaîne de caractères.';
        } elseif (($allRequired && $firstName === null) || $firstName === '') {
            $errors['firstName'][] = 'Le prénom est obligatoire.';
        } elseif ($firstName !== null && strlen($firstName) > 255) {
            $errors['firstName'][] = 'Le prénom ne peut pas dépasser 255 caractères.';
        }
        if (array_key_exists('lastName', $data) && !is_string($data['lastName'])) {
            $errors['lastName'][] = 'Le nom doit être une chaîne de caractères.';
        } elseif (($allRequired && $lastName === null) || $lastName === '') {
            $errors['lastName'][] = 'Le nom est obligatoire.';
        } elseif ($lastName !== null && strlen($lastName) > 255) {
            $errors['lastName'][] = 'Le nom ne peut pas dépasser 255 caractères.';
        }
        if (array_key_exists('email', $data) && !is_string($data['email'])) {
            $errors['email'][] = 'L’adresse email doit être une chaîne de caractères.';
        } elseif (($allRequired || $email !== null) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Adresse email invalide.';
        } elseif ($email !== null && strlen($email) > 180) {
            $errors['email'][] = 'L’adresse email ne peut pas dépasser 180 caractères.';
        } elseif (
            $email !== null
            && $userRepository->findOneByNormalizedEmail($email, $user?->getId()) instanceof User
        ) {
            $errors['email'][] = 'Cette adresse email est déjà utilisée.';
        }

        if ($roles !== null) {
            if (!is_array($roles)) {
                $errors['roles'][] = 'Les rôles doivent être un tableau.';
            } else {
                $invalidRoles = array_filter(
                    $roles,
                    static fn (mixed $role): bool => !is_string($role)
                        || !in_array($role, ['ROLE_USER', 'ROLE_ADMIN'], true)
                );
                if ($invalidRoles !== []) {
                    $errors['roles'][] = 'Les seuls rôles autorisés sont ROLE_USER et ROLE_ADMIN.';
                }
                $roles = array_values(array_unique(array_filter(
                    $roles,
                    static fn (mixed $role): bool => $role === 'ROLE_ADMIN'
                )));
            }
        } elseif ($allRequired) {
            $roles = [];
        }

        return compact('firstName', 'lastName', 'email', 'roles', 'errors');
    }

    private function validatePassword(
        string $password,
        string $passwordConfirmation,
        string $field = 'password',
    ): array {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[$field][] = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif (strlen($password) > 4096) {
            $errors[$field][] = 'Le mot de passe est trop long.';
        }
        if ($password !== $passwordConfirmation) {
            $errors['passwordConfirmation'][] = 'Les mots de passe ne correspondent pas.';
        }

        return $errors;
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
