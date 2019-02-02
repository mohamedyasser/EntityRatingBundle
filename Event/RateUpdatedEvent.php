<?php

namespace Yaso\Bundle\EntityRatingBundle\Event;

use Yaso\Bundle\EntityRatingBundle\Entity\EntityRate;
use Symfony\Component\EventDispatcher\Event;

class RateUpdatedEvent extends Event
{
    const NAME = 'yaso.entity_rating.rate_updated';
    /**
     * @var EntityRate
     */
    private $entityRate;

    public function __construct(EntityRate $entityRate)
    {
        $this->entityRate = $entityRate;
    }

    /**
     * @return EntityRate
     */
    public function getEntityRate(): EntityRate
    {
        return $this->entityRate;
    }

}