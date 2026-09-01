<?php

namespace App\Controller;

use App\Entity\RadiologyCase;
use App\Enum\CaseDifficulty;
use App\Enum\RadiologyCaseStatus;
use App\Repository\RadiologyCaseRepository;
use App\Service\RadiologyCaseInput;
use App\Service\RadiologyCaseNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/radiology-cases')]
#[IsGranted('ROLE_ADMIN')]
final class AdminRadiologyCaseController extends AbstractController
{
    #[Route('', name: 'api_admin_radiology_cases_list', methods: ['GET'])]
    public function list(Request $request, RadiologyCaseRepository $repository, RadiologyCaseNormalizer $normalizer): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        $difficultyValue = strtoupper(trim((string) $request->query->get('difficulty', '')));
        $statusValue = strtoupper(trim((string) $request->query->get('status', '')));
        $difficulty = $difficultyValue === '' ? null : CaseDifficulty::tryFrom($difficultyValue);
        $status = $statusValue === '' ? null : RadiologyCaseStatus::tryFrom($statusValue);
        if (($difficultyValue !== '' && $difficulty === null) || ($statusValue !== '' && $status === null)) {
            return $this->json(['message' => 'Un filtre enum est invalide.'], 400);
        }

        $result = $repository->findAdminPaginated(
            $page,
            $limit,
            trim((string) $request->query->get('search', '')),
            $this->positiveId($request, 'modality'),
            $this->positiveId($request, 'category'),
            $difficulty,
            $status,
            $this->positiveId($request, 'author'),
        );

        return $this->json([
            'items' => array_map(static fn ($case): array => $normalizer->normalize($case), $result['items']),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'totalItems' => $result['total'],
                'totalPages' => (int) ceil($result['total'] / $limit),
            ],
        ]);
    }

    #[Route('/{id<\d+>}', name: 'api_admin_radiology_cases_show', methods: ['GET'])]
    public function show(RadiologyCase $radiologyCase, RadiologyCaseNormalizer $normalizer): JsonResponse
    {
        return $this->json($normalizer->normalize($radiologyCase, true));
    }

    #[Route('', name: 'api_admin_radiology_cases_create', methods: ['POST'])]
    public function create(Request $request, RadiologyCaseInput $input, EntityManagerInterface $entityManager, RadiologyCaseNormalizer $normalizer): JsonResponse
    {
        $case = new RadiologyCase();
        $errors = $input->apply($case, $request->toArray(), true);
        if ($errors !== []) { return $this->validationError($errors); }

        $entityManager->persist($case);
        $entityManager->flush();

        return $this->json($normalizer->normalize($case, true), 201);
    }

    #[Route('/{id<\d+>}', name: 'api_admin_radiology_cases_update', methods: ['PATCH'])]
    public function update(RadiologyCase $radiologyCase, Request $request, RadiologyCaseInput $input, EntityManagerInterface $entityManager, RadiologyCaseNormalizer $normalizer): JsonResponse
    {
        $errors = $input->apply($radiologyCase, $request->toArray(), false);
        if ($errors !== []) { return $this->validationError($errors); }

        $entityManager->flush();
        return $this->json($normalizer->normalize($radiologyCase, true));
    }

    #[Route('/{id<\d+>}/publish', name: 'api_admin_radiology_cases_publish', methods: ['POST'])]
    public function publish(RadiologyCase $radiologyCase, RadiologyCaseInput $input, EntityManagerInterface $entityManager, RadiologyCaseNormalizer $normalizer): JsonResponse
    {
        $errors = $input->apply($radiologyCase, ['status' => RadiologyCaseStatus::PUBLISHED->value], false);
        if ($errors !== []) { return $this->validationError($errors); }
        $entityManager->flush();
        return $this->json($normalizer->normalize($radiologyCase, true));
    }

    #[Route('/{id<\d+>}/archive', name: 'api_admin_radiology_cases_archive', methods: ['POST'])]
    public function archive(RadiologyCase $radiologyCase, EntityManagerInterface $entityManager, RadiologyCaseNormalizer $normalizer): JsonResponse
    {
        $radiologyCase->setStatus(RadiologyCaseStatus::ARCHIVED);
        $entityManager->flush();
        return $this->json($normalizer->normalize($radiologyCase, true));
    }

    #[Route('/{id<\d+>}', name: 'api_admin_radiology_cases_delete', methods: ['DELETE'])]
    public function delete(RadiologyCase $radiologyCase, EntityManagerInterface $entityManager): JsonResponse
    {
        $entityManager->remove($radiologyCase);
        $entityManager->flush();
        return new JsonResponse(null, 204);
    }

    private function positiveId(Request $request, string $name): ?int
    {
        $value = $request->query->getInt($name, 0);
        return $value > 0 ? $value : null;
    }

    private function validationError(array $errors): JsonResponse
    {
        return $this->json(['message' => 'Les données envoyées sont invalides.', 'errors' => $errors], 422);
    }
}
