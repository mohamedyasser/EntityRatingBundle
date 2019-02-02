<?php

namespace Yaso\Bundle\EntityRatingBundle\Event;

use Yaso\Bundle\EntityRatingBundle\Entity\EntityRate;
use Yaso\Bundle\EntityRatingBundle\Entity\EntityRateInterface;
use Symfony\Component\EventDispatcher\Event;

class RateCreatedEvent extends Event
{
    const NAME = 'yaso.entity_rating.rate_created';
    /**
     * @var EntityRate
     */
    private $entityRate;

    public function __construct(EntityRateInterface $entityRate)
    {
        $this->entityRate = $entityRate;
    }

    /**
     * @return EntityRateInterface
     */
    public function getEntityRate(): EntityRateInterface
    {
        return $this->entityRate;
    }

}