<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{DemsExercise, Specialty};
use App\Service\EditorialContent;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class EditorialController extends AbstractController
{
    private const RESOURCE_PATTERN = 'specialties|classifications|courses|dems-exercises|report-exams|report-templates';

    #[Route('/api/content/{resource}', requirements: ['resource' => self::RESOURCE_PATTERN], methods: ['GET'])]
    #[Route('/api/admin/content/{resource}', requirements: ['resource' => self::RESOURCE_PATTERN], methods: ['GET'])]
    public function list(string $resource, Request $request, EntityManagerInterface $em, EditorialContent $content): JsonResponse
    {
        $admin = str_starts_with($request->getPathInfo(), '/api/admin/');
        if ($admin) { $this->denyAccessUnlessGranted('ROLE_ADMIN'); }
        $class = EditorialContent::RESOURCES[$resource];
        $qb = $em->getRepository($class)->createQueryBuilder('item');
        $search = trim((string) $request->query->get('search', ''));
        $field = $resource === 'courses' ? 'title' : ($resource === 'report-templates' ? 'label' : 'name');
        if ($search !== '') { $qb->andWhere("LOWER(item.$field) LIKE :search")->setParameter('search', '%'.mb_strtolower($search).'%'); }
        $filter = match ($resource) {
            'classifications', 'courses' => ['specialty', 'specialty'],
            'report-exams' => ['modality', 'modality'],
            'report-templates' => ['exam', 'exam'],
            default => null,
        };
        if ($filter && $request->query->get($filter[0]) !== null && $request->query->get($filter[0]) !== '') {
            $id = filter_var($request->query->get($filter[0]), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) { return $this->json(['message' => 'Filtre invalide.'], 400); }
            $qb->andWhere('IDENTITY(item.'.$filter[1].') = :filter')->setParameter('filter', $id);
        }
        $total = (int) (clone $qb)->select('COUNT(item.id)')->getQuery()->getSingleScalarResult();
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(200, max(1, $request->query->getInt('limit', 100)));
        if ($class === Specialty::class) { $qb->orderBy('item.position', 'ASC')->addOrderBy('item.id', 'ASC'); }
        else { $qb->orderBy('item.id', 'DESC'); }
        $items = $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit)->getQuery()->getResult();
        return $this->json(['items' => array_map(fn ($item) => $content->normalize($item, false, $admin), $items), 'pagination' => ['page' => $page, 'limit' => $limit, 'totalItems' => $total, 'totalPages' => (int) ceil($total / $limit)]]);
    }

    #[Route('/api/content/{resource}/{id<\d+>}', requirements: ['resource' => self::RESOURCE_PATTERN], methods: ['GET'])]
    #[Route('/api/admin/content/{resource}/{id<\d+>}', requirements: ['resource' => self::RESOURCE_PATTERN], methods: ['GET'])]
    public function show(string $resource, int $id, Request $request, EntityManagerInterface $em, EditorialContent $content): JsonResponse
    {
        $admin = str_starts_with($request->getPathInfo(), '/api/admin/');
        if ($admin) { $this->denyAccessUnlessGranted('ROLE_ADMIN'); }
        $item = $em->find(EditorialContent::RESOURCES[$resource], $id);
        if (!$item) { throw $this->createNotFoundException('Contenu introuvable.'); }
        return $this->json($content->normalize($item, true, $admin));
    }

    #[Route('/api/content/dems-exercises/{id<\d+>}/results', methods: ['POST'])]
    public function results(int $id, EntityManagerInterface $em, EditorialContent $content): JsonResponse
    {
        $item = $em->find(DemsExercise::class, $id);
        if (!$item) { throw $this->createNotFoundException('Planchage introuvable.'); }
        return $this->json(['answers' => $content->answers($item)], 200, ['Cache-Control' => 'no-store']);
    }

    #[Route('/api/admin/content/{resource}', requirements: ['resource' => self::RESOURCE_PATTERN], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(string $resource, Request $request, EntityManagerInterface $em, EditorialContent $content): JsonResponse
    {
        $data = $this->payload($request);
        $errors = $content->validate($resource, $data, true);
        if ($errors) { return $this->json(['message' => 'Données invalides.', 'errors' => $errors], 422); }
        $class = EditorialContent::RESOURCES[$resource];
        $item = new $class();
        $content->apply($item, $data);
        $em->persist($item);
        $em->flush();
        return $this->json($content->normalize($item, true, true), 201);
    }

    #[Route('/api/admin/content/{resource}/{id<\d+>}', requirements: ['resource' => self::RESOURCE_PATTERN], methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(string $resource, int $id, Request $request, EntityManagerInterface $em, EditorialContent $content): JsonResponse
    {
        $item = $em->find(EditorialContent::RESOURCES[$resource], $id);
        if (!$item) { throw $this->createNotFoundException('Contenu introuvable.'); }
        $data = $this->payload($request);
        $errors = $content->validate($resource, $data, false);
        if ($errors) { return $this->json(['message' => 'Données invalides.', 'errors' => $errors], 422); }
        $content->apply($item, $data);
        $em->flush();
        return $this->json($content->normalize($item, true, true));
    }

    #[Route('/api/admin/content/{resource}/{id<\d+>}', requirements: ['resource' => self::RESOURCE_PATTERN], methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(string $resource, int $id, EntityManagerInterface $em): JsonResponse
    {
        $item = $em->find(EditorialContent::RESOURCES[$resource], $id);
        if (!$item) { throw $this->createNotFoundException('Contenu introuvable.'); }
        try {
            $em->remove($item);
            $em->flush();
        } catch (ForeignKeyConstraintViolationException) {
            return $this->json(['message' => 'Suppression impossible : ce contenu est encore utilisé. Retirez ses dépendances avant de le supprimer.'], 409);
        }
        return new JsonResponse(null, 204);
    }

    private function payload(Request $request): array
    {
        if (strlen($request->getContent()) > 2_000_000) { throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Contenu trop volumineux.'); }
        $data = $request->toArray();
        if (array_is_list($data) && $data !== []) { throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Un objet JSON est attendu.'); }
        return $data;
    }
}
