<?php

namespace App\Repository;

use App\Entity\WeatherData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WeatherData>
 */
class WeatherDataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeatherData::class);
    }

    /**
     * Find weather data by city name
     *
     * @param string $city
     * @return WeatherData|null
     */
    public function findByCity(string $city): ?WeatherData
    {
        return $this->createQueryBuilder('w')
            ->andWhere('LOWER(w.city) = LOWER(:city)')
            ->setParameter('city', $city)
            ->orderBy('w.lastUpdated', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find recent weather data by city name
     *
     * @param string $city
     * @param int $maxAgeMinutes
     * @return WeatherData|null
     */
    public function findRecentByCity(string $city, int $maxAgeMinutes = 30): ?WeatherData
    {
        $cutoffTime = new \DateTime();
        $cutoffTime->modify(sprintf('-%d minutes', $maxAgeMinutes));

        return $this->createQueryBuilder('w')
            ->andWhere('LOWER(w.city) = LOWER(:city)')
            ->andWhere('w.lastUpdated >= :cutoffTime')
            ->setParameter('city', $city)
            ->setParameter('cutoffTime', $cutoffTime)
            ->orderBy('w.lastUpdated', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Clean old weather data records
     *
     * @param int $daysToKeep
     * @return int Number of deleted records
     */
    public function cleanOldRecords(int $daysToKeep = 7): int
    {
        $cutoffDate = new \DateTime();
        $cutoffDate->modify(sprintf('-%d days', $daysToKeep));

        $qb = $this->createQueryBuilder('w');
        
        return $qb->delete()
            ->where('w.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->execute();
    }

    /**
     * Save weather data
     *
     * @param WeatherData $weatherData
     * @param bool $flush
     * @return void
     */
    public function save(WeatherData $weatherData, bool $flush = false): void
    {
        $this->getEntityManager()->persist($weatherData);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove weather data
     *
     * @param WeatherData $weatherData
     * @param bool $flush
     * @return void
     */
    public function remove(WeatherData $weatherData, bool $flush = false): void
    {
        $this->getEntityManager()->remove($weatherData);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}