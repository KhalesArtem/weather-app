<?php

namespace App\Service;

use App\Entity\WeatherData;
use App\Repository\WeatherDataRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class WeatherCacheService
{
    public function __construct(
        private WeatherDataRepository $weatherDataRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Check if weather data is still fresh
     *
     * @param WeatherData $data
     * @param int $maxAgeMinutes
     * @return bool
     */
    public function isDataFresh(WeatherData $data, int $maxAgeMinutes = 30): bool
    {
        $now = new \DateTime();
        $lastUpdated = $data->getLastUpdated();
        
        if (!$lastUpdated) {
            return false;
        }

        $diff = $now->diff($lastUpdated);
        $minutesDiff = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;

        $isFresh = $minutesDiff <= $maxAgeMinutes;

        $this->logger->debug('Checking data freshness', [
            'city' => $data->getCity(),
            'last_updated' => $lastUpdated->format('Y-m-d H:i:s'),
            'minutes_old' => $minutesDiff,
            'max_age_minutes' => $maxAgeMinutes,
            'is_fresh' => $isFresh
        ]);

        return $isFresh;
    }

    /**
     * Save weather data to cache
     *
     * @param array $data
     * @return WeatherData
     */
    public function saveWeatherData(array $data): WeatherData
    {
        $this->logger->info('Saving weather data to cache', ['city' => $data['city']]);

        // Check if we already have data for this city
        $existingData = $this->weatherDataRepository->findByCity($data['city']);

        if ($existingData) {
            // Update existing record
            $weatherData = $existingData;
            $this->logger->debug('Updating existing weather data', ['city' => $data['city']]);
        } else {
            // Create new record
            $weatherData = new WeatherData();
            $weatherData->setCreatedAt(new \DateTime());
            $this->logger->debug('Creating new weather data record', ['city' => $data['city']]);
        }

        // Update fields
        $weatherData->setCity($data['city']);
        $weatherData->setCountry($data['country']);
        $weatherData->setTemperature($data['temperature']);
        $weatherData->setCondition($data['condition']);
        $weatherData->setHumidity($data['humidity']);
        $weatherData->setWindSpeed($data['wind_speed']);
        $weatherData->setLastUpdated(new \DateTime());
        $weatherData->setApiLastUpdated($data['last_updated']);

        $this->entityManager->persist($weatherData);
        $this->entityManager->flush();

        $this->logger->info('Weather data saved successfully', [
            'city' => $data['city'],
            'id' => $weatherData->getId()
        ]);

        return $weatherData;
    }

    /**
     * Clear cache for a specific city
     *
     * @param string $city
     * @return void
     */
    public function clearCacheForCity(string $city): void
    {
        $this->logger->info('Clearing weather cache for city', ['city' => $city]);

        $weatherData = $this->weatherDataRepository->findByCity($city);

        if ($weatherData) {
            $this->entityManager->remove($weatherData);
            $this->entityManager->flush();

            $this->logger->info('Weather cache cleared', [
                'city' => $city,
                'id' => $weatherData->getId()
            ]);
        } else {
            $this->logger->debug('No cache found for city', ['city' => $city]);
        }
    }

    /**
     * Clear all old cache records
     *
     * @param int $daysToKeep
     * @return int Number of deleted records
     */
    public function clearOldCache(int $daysToKeep = 7): int
    {
        $this->logger->info('Clearing old weather cache', ['days_to_keep' => $daysToKeep]);

        $deletedCount = $this->weatherDataRepository->cleanOldRecords($daysToKeep);

        $this->logger->info('Old weather cache cleared', [
            'deleted_count' => $deletedCount,
            'days_to_keep' => $daysToKeep
        ]);

        return $deletedCount;
    }
}