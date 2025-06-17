<?php

namespace App\Service;

use App\Entity\WeatherData;
use App\Exception\WeatherApiException;
use App\Repository\WeatherDataRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class WeatherService
{
    private int $cacheMaxAgeMinutes;

    public function __construct(
        private WeatherApiClient $weatherApiClient,
        private WeatherCacheService $weatherCacheService,
        private WeatherDataRepository $weatherDataRepository,
        private LoggerInterface $logger,
        #[Autowire('%env(int:WEATHER_CACHE_MAX_AGE_MINUTES)%')]
        int $cacheMaxAgeMinutes = 30
    ) {
        $this->cacheMaxAgeMinutes = $cacheMaxAgeMinutes;
    }

    /**
     * Get weather data for a city (from cache or API)
     *
     * @param string $city
     * @param bool $forceRefresh Force API call even if cache is fresh
     * @return array
     * @throws WeatherApiException
     */
    public function getWeather(string $city, bool $forceRefresh = false): array
    {
        $this->logger->info('Getting weather data', [
            'city' => $city,
            'force_refresh' => $forceRefresh
        ]);

        // Try to get from cache first
        if (!$forceRefresh) {
            $cachedData = $this->getFromCache($city);
            if ($cachedData !== null) {
                $this->logger->info('Returning weather data from cache', ['city' => $city]);
                return $cachedData;
            }
        }

        // Cache miss or forced refresh - fetch from API
        $this->logger->info('Fetching weather data from API', ['city' => $city]);

        try {
            $apiData = $this->weatherApiClient->getCurrentWeather($city);
            
            // Save to cache
            $weatherData = $this->weatherCacheService->saveWeatherData($apiData);
            
            return $this->formatWeatherData($weatherData, $apiData);

        } catch (WeatherApiException $e) {
            $this->logger->error('Failed to fetch weather from API', [
                'city' => $city,
                'error' => $e->getMessage(),
                'context' => $e->getContext()
            ]);

            // If API fails, try to return stale cache data if available
            $staleData = $this->weatherDataRepository->findByCity($city);
            if ($staleData) {
                $this->logger->warning('Returning stale cache data due to API error', [
                    'city' => $city,
                    'last_updated' => $staleData->getLastUpdated()->format('Y-m-d H:i:s')
                ]);

                return $this->formatWeatherData($staleData, [], true);
            }

            throw $e;
        }
    }

    /**
     * Get weather data from cache if fresh
     *
     * @param string $city
     * @return array|null
     */
    private function getFromCache(string $city): ?array
    {
        $cachedData = $this->weatherDataRepository->findRecentByCity($city, $this->cacheMaxAgeMinutes);

        if ($cachedData === null) {
            $this->logger->debug('Cache miss for city', ['city' => $city]);
            return null;
        }

        if ($this->weatherCacheService->isDataFresh($cachedData, $this->cacheMaxAgeMinutes)) {
            $this->logger->debug('Fresh cache hit for city', ['city' => $city]);
            return $this->formatWeatherData($cachedData);
        }

        $this->logger->debug('Cache hit but data is stale', ['city' => $city]);
        return null;
    }

    /**
     * Format weather data for response
     *
     * @param WeatherData $weatherData
     * @param array $additionalData
     * @param bool $isStale
     * @return array
     */
    private function formatWeatherData(
        WeatherData $weatherData,
        array $additionalData = [],
        bool $isStale = false
    ): array {
        $data = [
            'city' => $weatherData->getCity(),
            'country' => $weatherData->getCountry(),
            'temperature' => $weatherData->getTemperature(),
            'condition' => $weatherData->getCondition(),
            'humidity' => $weatherData->getHumidity(),
            'wind_speed' => $weatherData->getWindSpeed(),
            'last_updated' => $weatherData->getLastUpdated()->format('Y-m-d H:i:s'),
            'api_last_updated' => $weatherData->getApiLastUpdated(),
            'cached' => true,
            'cache_age_minutes' => $this->getCacheAgeMinutes($weatherData),
            'stale' => $isStale
        ];

        // Add additional data from API response if available
        if (!empty($additionalData)) {
            $data['local_time'] = $additionalData['local_time'] ?? null;
            $data['timezone'] = $additionalData['timezone'] ?? null;
            $data['icon'] = $additionalData['icon'] ?? null;
        }

        return $data;
    }

    /**
     * Calculate cache age in minutes
     *
     * @param WeatherData $weatherData
     * @return int
     */
    private function getCacheAgeMinutes(WeatherData $weatherData): int
    {
        $now = new \DateTime();
        $lastUpdated = $weatherData->getLastUpdated();
        
        if (!$lastUpdated) {
            return 0;
        }

        $diff = $now->diff($lastUpdated);
        return ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
    }

    /**
     * Clear cache for a specific city
     *
     * @param string $city
     * @return void
     */
    public function clearCache(string $city): void
    {
        $this->logger->info('Clearing weather cache', ['city' => $city]);
        $this->weatherCacheService->clearCacheForCity($city);
    }

    /**
     * Get cache statistics
     *
     * @return array
     */
    public function getCacheStats(): array
    {
        $totalRecords = count($this->weatherDataRepository->findAll());
        $freshRecords = count(
            $this->weatherDataRepository->createQueryBuilder('w')
                ->where('w.lastUpdated >= :cutoff')
                ->setParameter('cutoff', new \DateTime('-' . $this->cacheMaxAgeMinutes . ' minutes'))
                ->getQuery()
                ->getResult()
        );

        return [
            'total_cached_cities' => $totalRecords,
            'fresh_cache_entries' => $freshRecords,
            'stale_cache_entries' => $totalRecords - $freshRecords,
            'cache_max_age_minutes' => $this->cacheMaxAgeMinutes
        ];
    }
}