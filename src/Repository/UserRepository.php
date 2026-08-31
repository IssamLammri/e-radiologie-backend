<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByNormalizedEmail(string $email, ?int $excludedUserId = null): ?User
    {
        $queryBuilder = $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.email) = :email')
            ->setParameter('email', strtolower(trim($email)))
            ->setMaxResults(1);

        if ($excludedUserId !== null) {
            $queryBuilder
                ->andWhere('u.id != :excludedUserId')
                ->setParameter('excludedUserId', $excludedUserId);
        }

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    /**
     * @return array{items: list<User>, total: int}
     */
    public function findPaginated(int $page, int $limit, string $search = ''): array
    {
        $queryBuilder = $this->createQueryBuilder('u')
            ->orderBy('u.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($search !== '') {
            $queryBuilder
                ->andWhere(
                    'LOWER(u.email) LIKE :search '
                    .'OR LOWER(u.firstName) LIKE :search '
                    .'OR LOWER(u.lastName) LIKE :search'
                )
                ->setParameter('search', '%'.strtolower($search).'%');
        }

        $paginator = new Paginator($queryBuilder->getQuery());

        return [
            'items' => array_values(iterator_to_array($paginator)),
            'total' => count($paginator),
        ];
    }
}
