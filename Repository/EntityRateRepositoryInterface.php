<?php

namespace Yaso\Bundle\EntityRatingBundle\Repository;

interface EntityRateRepositoryInterface
{
    public function getEntityAverageRate($entityId, $entityType);

    public function getRateByIpAndUserAgent($ip, $userAgent, $entityId, $entityType, $ignoreFields);
}