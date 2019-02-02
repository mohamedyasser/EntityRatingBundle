<?php

namespace Yaso\Bundle\EntityRatingBundle\Repository;

use Doctrine\ORM\EntityRepository;

class EntityRateRepository extends EntityRepository implements EntityRateRepositoryInterface
{
    /**
     * @param $entityId
     * @param $entityType
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getEntityAverageRate($entityId, $entityType)
    {
        return $this->createQueryBuilder('er')
            ->select('avg(er.rate) as average_rate, count(er.id) as rate_count')
            ->where('er.entityType = :entity_type')
            ->setParameter('entity_type', $entityType)
            ->andWhere('er.entityId = :entity_id')
            ->setParameter('entity_id', $entityId)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * @param $ip
     * @param $userAgent
     * @param $entityId
     * @param $entityType
     * @param $ignoreFields
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getRateByIpAndUserAgent($ip, $userAgent, $entityId, $entityType, $ignoreFields)
    {
        $q = $this->createQueryBuilder('er')
            ->where('er.entityType = :entity_type')
            ->setParameter('entity_type', $entityType)
            ->andWhere('er.entityId = :entity_id')
            ->setParameter('entity_id', $entityId)
            ->andWhere('er.userAgent = :user_agent')
            ->setParameter('user_agent', $userAgent)
            ->andWhere('er.ip = :ip')
            ->setParameter('ip', $ip);

        if (!empty($ignoreFields)) {
            foreach ($ignoreFields as $ignoreField) {
                $q->andWhere('er.'.$ignoreField.' is null');
            }
        }

        return $q->getQuery()
            ->getOneOrNullResult();
    }

}