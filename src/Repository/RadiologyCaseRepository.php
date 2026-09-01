<?php

namespace App\Repository;

use App\Entity\RadiologyCase;
use App\Enum\CaseDifficulty;
use App\Enum\RadiologyCaseStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

class RadiologyCaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RadiologyCase::class);
    }

    /** @return list<RadiologyCase> */
    public function findRecentPublished(int $limit = 3): array
    {
        return $this->publishedQueryBuilder()
            ->orderBy('rc.publishedAt', 'DESC')
            ->addOrderBy('rc.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findPublishedBySlug(string $slug): ?RadiologyCase
    {
        return $this->publishedQueryBuilder()
            ->andWhere('rc.slug = :slug')
            ->setParameter('slug', $slug)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return array{items: list<RadiologyCase>, total: int} */
    public function findPublishedPaginated(
        int $page,
        int $limit,
        string $search = '',
        ?int $modalityId = null,
        ?int $categoryId = null,
        ?CaseDifficulty $difficulty = null,
    ): array {
        $queryBuilder = $this->publishedQueryBuilder();
        $this->applyFilters($queryBuilder, $search, $modalityId, $categoryId, $difficulty, null, null);

        return $this->paginate($queryBuilder
            ->orderBy('rc.publishedAt', 'DESC')
            ->addOrderBy('rc.id', 'DESC'), $page, $limit);
    }

    /** @return array{items: list<RadiologyCase>, total: int} */
    public function findAdminPaginated(
        int $page,
        int $limit,
        string $search = '',
        ?int $modalityId = null,
        ?int $categoryId = null,
        ?CaseDifficulty $difficulty = null,
        ?RadiologyCaseStatus $status = null,
        ?int $authorId = null,
    ): array {
        $queryBuilder = $this->createQueryBuilder('rc')
            ->addSelect('modality', 'category', 'author')
            ->join('rc.modality', 'modality')
            ->join('rc.category', 'category')
            ->join('rc.author', 'author');
        $this->applyFilters($queryBuilder, $search, $modalityId, $categoryId, $difficulty, $status, $authorId);

        return $this->paginate($queryBuilder
            ->orderBy('rc.createdAt', 'DESC')
            ->addOrderBy('rc.id', 'DESC'), $page, $limit);
    }

    public function createUniqueSlug(string $baseSlug, ?int $excludedId = null): string
    {
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'cas-clinique';
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($slug, $excludedId)) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        return $slug;
    }

    private function publishedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('rc')
            ->addSelect('modality', 'category', 'author')
            ->join('rc.modality', 'modality')
            ->join('rc.category', 'category')
            ->join('rc.author', 'author')
            ->andWhere('rc.status = :published')
            ->setParameter('published', RadiologyCaseStatus::PUBLISHED);
    }

    private function applyFilters(
        QueryBuilder $queryBuilder,
        string $search,
        ?int $modalityId,
        ?int $categoryId,
        ?CaseDifficulty $difficulty,
        ?RadiologyCaseStatus $status,
        ?int $authorId,
    ): void {
        if ($search !== '') {
            $queryBuilder
                ->andWhere('LOWER(rc.title) LIKE :search OR LOWER(rc.clinicalContext) LIKE :search OR LOWER(rc.diagnosis) LIKE :search')
                ->setParameter('search', '%'.strtolower($search).'%');
        }
        if ($modalityId !== null) {
            $queryBuilder->andWhere('modality.id = :modalityId')->setParameter('modalityId', $modalityId);
        }
        if ($categoryId !== null) {
            $queryBuilder->andWhere('category.id = :categoryId')->setParameter('categoryId', $categoryId);
        }
        if ($difficulty !== null) {
            $queryBuilder->andWhere('rc.difficulty = :difficulty')->setParameter('difficulty', $difficulty);
        }
        if ($status !== null) {
            $queryBuilder->andWhere('rc.status = :status')->setParameter('status', $status);
        }
        if ($authorId !== null) {
            $queryBuilder->andWhere('author.id = :authorId')->setParameter('authorId', $authorId);
        }
    }

    /** @return array{items: list<RadiologyCase>, total: int} */
    private function paginate(QueryBuilder $queryBuilder, int $page, int $limit): array
    {
        $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        $paginator = new Paginator($queryBuilder->getQuery());

        return [
            'items' => array_values(iterator_to_array($paginator)),
            'total' => count($paginator),
        ];
    }

    private function slugExists(string $slug, ?int $excludedId): bool
    {
        $queryBuilder = $this->createQueryBuilder('rc')
            ->select('COUNT(rc.id)')
            ->andWhere('rc.slug = :slug')
            ->setParameter('slug', $slug);
        if ($excludedId !== null) {
            $queryBuilder->andWhere('rc.id != :id')->setParameter('id', $excludedId);
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult() > 0;
    }
}
