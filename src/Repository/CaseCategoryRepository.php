<?php

namespace App\Repository;

use App\Entity\CaseCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CaseCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, CaseCategory::class); }

    /** @return list<CaseCategory> */
    public function findActiveOrdered(): array
    {
        return $this->findBy(['active' => true], ['position' => 'ASC', 'name' => 'ASC']);
    }
}
