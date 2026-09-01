<?php

namespace App\Controller;

use App\Enum\CaseDifficulty;
use App\Repository\CaseCategoryRepository;
use App\Repository\ImagingModalityRepository;
use App\Repository\RadiologyCaseRepository;
use App\Service\RadiologyCaseNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class PublicRadiologyCaseController extends AbstractController
{
    #[Route('/cases/recent', name: 'api_public_cases_recent', methods: ['GET'])]
    public function recent(RadiologyCaseRepository $repository, RadiologyCaseNormalizer $normalizer): JsonResponse
    {
        return $this->json([
            'items' => array_map(
                static fn ($case): array => $normalizer->normalize($case),
                $repository->findRecentPublished(3),
            ),
        ]);
    }

    #[Route('/cases', name: 'api_public_cases_list', methods: ['GET'])]
    public function list(Request $request, RadiologyCaseRepository $repository, RadiologyCaseNormalizer $normalizer): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        $difficultyValue = strtoupper(trim((string) $request->query->get('difficulty', '')));
        $difficulty = $difficultyValue === '' ? null : CaseDifficulty::tryFrom($difficultyValue);
        if ($difficultyValue !== '' && $difficulty === null) {
            return $this->json(['message' => 'Le niveau demandé est invalide.'], 400);
        }

        $result = $repository->findPublishedPaginated(
            $page,
            $limit,
            trim((string) $request->query->get('search', '')),
            $this->positiveId($request, 'modality'),
            $this->positiveId($request, 'category'),
            $difficulty,
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

    #[Route('/cases/{slug}', name: 'api_public_cases_show', methods: ['GET'], requirements: ['slug' => '[a-z0-9-]+'])]
    public function show(string $slug, RadiologyCaseRepository $repository, RadiologyCaseNormalizer $normalizer): JsonResponse
    {
        $case = $repository->findPublishedBySlug($slug);
        if ($case === null) {
            throw $this->createNotFoundException('Cas clinique introuvable.');
        }

        return $this->json($normalizer->normalize($case, true));
    }

    #[Route('/imaging-modalities', name: 'api_public_modalities_list', methods: ['GET'])]
    public function modalities(ImagingModalityRepository $repository, RadiologyCaseNormalizer $normalizer): JsonResponse
    {
        return $this->json(['items' => array_map($normalizer->modality(...), $repository->findActiveOrdered())]);
    }

    #[Route('/case-categories', name: 'api_public_categories_list', methods: ['GET'])]
    public function categories(CaseCategoryRepository $repository, RadiologyCaseNormalizer $normalizer): JsonResponse
    {
        return $this->json(['items' => array_map($normalizer->category(...), $repository->findActiveOrdered())]);
    }

    private function positiveId(Request $request, string $name): ?int
    {
        $value = $request->query->get($name);
        if ($value === null || $value === '') {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
            'flags' => FILTER_NULL_ON_FAILURE,
        ]);

        return $id;
    }
}
