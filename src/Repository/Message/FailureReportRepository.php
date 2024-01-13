<?php

namespace App\Repository\Message;

use App\Entity\Message\FailureReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FailureReport>
 *
 * @method FailureReport|null find($id, $lockMode = null, $lockVersion = null)
 * @method FailureReport|null findOneBy(array $criteria, array $orderBy = null)
 * @method FailureReport[]    findAll()
 * @method FailureReport[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FailureReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FailureReport::class);
    }

    //    /**
    //     * @return FailureReport[] Returns an array of FailureReport objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('f.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?FailureReport
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
