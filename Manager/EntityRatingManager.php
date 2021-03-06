<?php

namespace Yaso\Bundle\EntityRatingBundle\Manager;

use Yaso\Bundle\EntityRatingBundle\Annotation\Rated;
use Yaso\Bundle\EntityRatingBundle\Entity\EntityRate;
use Yaso\Bundle\EntityRatingBundle\Entity\EntityRateInterface;
use Yaso\Bundle\EntityRatingBundle\Event\RateCreatedEvent;
use Yaso\Bundle\EntityRatingBundle\Event\RateUpdatedEvent;
use Yaso\Bundle\EntityRatingBundle\Exception\EntityRateIpLimitationReachedException;
use Yaso\Bundle\EntityRatingBundle\Exception\UndeclaredEntityRatingTypeException;
use Yaso\Bundle\EntityRatingBundle\Exception\UnsupportedEntityRatingClassException;
use Yaso\Bundle\EntityRatingBundle\Factory\EntityRatingFormFactory;
use Yaso\Bundle\EntityRatingBundle\Repository\EntityRateRepository;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManager;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EntityRatingManager
{
    /**
     * @var AnnotationReader
     */
    protected $annotationReader;
    /**
     * @var EntityRatingFormFactory
     */
    protected $formFactory;
    /**
     * @var EntityRateRepository
     */
    protected $entityRateRepository;
    /**
     * @var EventDispatcher
     */
    protected $eventDispatcher;

    protected $entityRatingClass;
    protected $mapTypeToClass;
    protected $rateByIpLimitation;

    public function __construct(
        AnnotationReader $annotationReader,
        EntityRatingFormFactory $formFactory,
        EventDispatcherInterface $eventDispatcher,
        EntityManager $entityManager,
        $entityRatingClass,
        $mapTypeToClass,
        $rateByIpLimitation
    ) {
        $this->annotationReader = $annotationReader;
        $this->formFactory      = $formFactory;
        $this->eventDispatcher  = $eventDispatcher;
        $this->entityManager    = $entityManager;

        $this->userIp    = $_SERVER['REMOTE_ADDR'];
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $this->entityRatingClass    = $entityRatingClass;
        $this->mapTypeToClass       = $mapTypeToClass;
        $this->rateByIpLimitation   = $rateByIpLimitation;
        $this->entityRateRepository = $this->entityManager->getRepository($this->entityRatingClass);
    }

    /**
     * @param $entityType
     * @param $entityId
     * @param $rateValue
     * @throws EntityRateIpLimitationReachedException
     * @throws UndeclaredEntityRatingTypeException
     * @throws UnsupportedEntityRatingClassException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \ReflectionException
     */
    public function rate($entityType, $entityId, $rateValue)
    {
        $this->checkConfiguration($entityType);

        /** @var EntityRate $rate */
        $rate = $this->getUserCurrentRate($entityId, $entityType);

        if ($rate) {
            $this->updateRate($rate, $rateValue);
        } else {
            if ($this->allowRatingEntity($entityId, $entityType)) {
                $this->addRate($entityId, $entityType, $rateValue);
            } else {
                throw new EntityRateIpLimitationReachedException('Rating quota reached for this IP address and object.');
            }
        }
    }

    /**
     * @param $entityId
     * @param $entityType
     * @param $rateValue
     * @throws UndeclaredEntityRatingTypeException
     * @throws UnsupportedEntityRatingClassException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \ReflectionException
     */
    protected function addRate($entityId, $entityType, $rateValue)
    {
        $this->checkConfiguration($entityType);

        /** @var EntityRateInterface $rate */
        $rate = $this->hydrateEntity(new $this->entityRatingClass(), $entityId, $entityType, $rateValue);

        $this->entityManager->persist($rate);
        $this->entityManager->flush();
        $this->eventDispatcher->dispatch(RateCreatedEvent::NAME, new RateCreatedEvent($rate));
    }

    /**
     * @param EntityRate $rate
     * @param $rateValue
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     */
    protected function updateRate(EntityRate $rate, $rateValue)
    {
        $rate->setRate($rateValue);
        $rate->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();
        $this->eventDispatcher->dispatch(RateUpdatedEvent::NAME, new RateUpdatedEvent($rate));
    }

    protected function hydrateEntity(EntityRateInterface $rate, $entityId, $entityType, $rateValue)
    {
        $rate->setEntityId($entityId);
        $rate->setEntityType($entityType);
        $rate->setRate($rateValue);
        $rate->setIp($this->userIp);
        $rate->setUserAgent($this->userAgent);

        return $rate;
    }

    /**
     * @param $entityType
     * @param $entityId
     * @param null $formName
     * @return \Symfony\Component\Form\FormInterface
     * @throws UndeclaredEntityRatingTypeException
     * @throws UnsupportedEntityRatingClassException
     * @throws \ReflectionException
     */
    public function generateForm($entityType, $entityId, $formName = null)
    {
        $annotation = $this->checkConfiguration($entityType);

        return $this->formFactory->getForm($annotation, $entityType, $entityId, $formName);
    }

    /**
     * @param $entityType
     * @return bool
     * @throws UndeclaredEntityRatingTypeException
     * @throws UnsupportedEntityRatingClassException
     * @throws \ReflectionException
     */
    protected function checkConfiguration($entityType)
    {
        if (false === array_key_exists($entityType, $this->mapTypeToClass)) {
            throw new UndeclaredEntityRatingTypeException(sprintf('You must declare the %s type and the corresponding class under the yaso_entity_rating.map_type_to_class configuration key.', $entityType));
        }

        if (false === $annotation = $this->typeIsSupported($this->mapTypeToClass[$entityType])) {
            throw new UnsupportedEntityRatingClassException(sprintf('Class does not support EntityRating, you must add the `Rated` annotation to the %s class first', $this->mapTypeToClass[$entityType]));
        }

        return $annotation;
    }

    /**
     * @param $entityClass
     * @return bool
     * @throws \ReflectionException
     */
    protected function typeIsSupported($entityClass)
    {
        $reflClass        = new \ReflectionClass($entityClass);
        $classAnnotations = $this->annotationReader->getClassAnnotations($reflClass);

        foreach ($classAnnotations AS $annot) {
            if ($annot instanceof Rated) {
                return $annot;
            }
        }

        return false;
    }

    /**
     * @param $entityId
     * @param $entityType
     * @return array
     * @throws UndeclaredEntityRatingTypeException
     * @throws UnsupportedEntityRatingClassException
     * @throws \ReflectionException
     */
    public function getGlobalRateData($entityId, $entityType)
    {
        /** @var Rated $annotation */
        $annotation        = $this->checkConfiguration($entityType);
        $averageRateResult = $this->entityRateRepository->getEntityAverageRate($entityId, $entityType);

        return [
            'averageRate' => (float)round($averageRateResult['average_rate'], 1),
            'rateCount'   => (int)$averageRateResult['rate_count'],
            'minRate'     => $annotation->getMin(),
            'maxRate'     => $annotation->getMax(),
        ];
    }

    protected function allowRatingEntity($entityId, $entityType)
    {
        $rateByIpLimitation = $this->rateByIpLimitation;

        if ($rateByIpLimitation == 0) {
            return true;
        }

        $rates = $this->entityRateRepository->findBy(
            [
                'entityId'   => $entityId,
                'entityType' => $entityType,
                'ip'         => $this->userIp,
            ]
        );

        return count($rates) < $this->rateByIpLimitation;
    }

    public function getUserCurrentRate($entityId, $entityType, $ignoreFields = [])
    {
        return $this->entityRateRepository->getRateByIpAndUserAgent($this->userIp, $this->userAgent, $entityId, $entityType, $ignoreFields);
    }

    /**
     * @return EntityRateRepository
     */
    public function getEntityRateRepository(): EntityRateRepository
    {
        return $this->entityRateRepository;
    }

}