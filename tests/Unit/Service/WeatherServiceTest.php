<?php

namespace App\Tests\Unit\Service;

use App\Entity\WeatherData;
use App\Exception\WeatherApiException;
use App\Repository\WeatherDataRepository;
use App\Service\WeatherApiClient;
use App\Service\WeatherCacheService;
use App\Service\WeatherService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WeatherServiceTest extends TestCase
{
    private WeatherApiClient $weatherApiClient;
    private WeatherCacheService $weatherCacheService;
    private WeatherDataRepository $weatherDataRepository;
    private LoggerInterface $logger;
    private WeatherService $weatherService;

    protected function setUp(): void
    {
        $this->weatherApiClient = $this->createMock(WeatherApiClient::class);
        $this->weatherCacheService = $this->createMock(WeatherCacheService::class);
        $this->weatherDataRepository = $this->createMock(WeatherDataRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->weatherService = new WeatherService(
            $this->weatherApiClient,
            $this->weatherCacheService,
            $this->weatherDataRepository,
            $this->logger,
            30 // cache TTL in minutes
        );
    }

    public function testCacheHitReturnsFromDatabase(): void
    {
        // Arrange
        $city = 'London';
        $weatherData = $this->createWeatherDataEntity($city);

        $this->weatherDataRepository
            ->expects($this->once())
            ->method('findRecentByCity')
            ->with($city, 30)
            ->willReturn($weatherData);

        $this->weatherCacheService
            ->expects($this->once())
            ->method('isDataFresh')
            ->with($weatherData, 30)
            ->willReturn(true);

        // API should not be called
        $this->weatherApiClient
            ->expects($this->never())
            ->method('getCurrentWeather');

        // Act
        $result = $this->weatherService->getWeather($city);

        // Assert
        $this->assertEquals('London', $result['city']);
        $this->assertEquals('United Kingdom', $result['country']);
        $this->assertEquals(15.5, $result['temperature']);
        $this->assertEquals('Partly cloudy', $result['condition']);
        $this->assertTrue($result['cached']);
        $this->assertFalse($result['stale']);
    }

    public function testCacheMissFetchesFromApi(): void
    {
        // Arrange
        $city = 'Paris';
        $apiData = [
            'city' => 'Paris',
            'country' => 'France',
            'temperature' => 18.0,
            'condition' => 'Sunny',
            'humidity' => 45,
            'wind_speed' => 8.0,
            'last_updated' => '2024-01-15 14:00',
            'local_time' => '2024-01-15 15:00',
            'timezone' => 'Europe/Paris',
            'icon' => '//cdn.weatherapi.com/weather/64x64/day/113.png'
        ];

        $weatherData = $this->createWeatherDataEntity('Paris');
        $weatherData->setCountry('France');
        $weatherData->setTemperature(18.0);

        $this->weatherDataRepository
            ->expects($this->once())
            ->method('findRecentByCity')
            ->with($city, 30)
            ->willReturn(null);

        $this->weatherApiClient
            ->expects($this->once())
            ->method('getCurrentWeather')
            ->with($city)
            ->willReturn($apiData);

        $this->weatherCacheService
            ->expects($this->once())
            ->method('saveWeatherData')
            ->with($apiData)
            ->willReturn($weatherData);

        // Act
        $result = $this->weatherService->getWeather($city);

        // Assert
        $this->assertEquals('Paris', $result['city']);
        $this->assertEquals('France', $result['country']);
        $this->assertEquals(18.0, $result['temperature']);
        $this->assertTrue($result['cached']);
        $this->assertFalse($result['stale']);
    }

    public function testExpiredCacheRefetchesFromApi(): void
    {
        // Arrange
        $city = 'Tokyo';
        $staleWeatherData = $this->createWeatherDataEntity($city);
        
        $apiData = [
            'city' => 'Tokyo',
            'country' => 'Japan',
            'temperature' => 25.0,
            'condition' => 'Humid',
            'humidity' => 75,
            'wind_speed' => 10.0,
            'last_updated' => '2024-01-15 14:00',
            'local_time' => '2024-01-15 22:00',
            'timezone' => 'Asia/Tokyo',
            'icon' => null
        ];

        $freshWeatherData = $this->createWeatherDataEntity($city);

        $this->weatherDataRepository
            ->expects($this->once())
            ->method('findRecentByCity')
            ->with($city, 30)
            ->willReturn($staleWeatherData);

        $this->weatherCacheService
            ->expects($this->once())
            ->method('isDataFresh')
            ->with($staleWeatherData, 30)
            ->willReturn(false);

        $this->weatherApiClient
            ->expects($this->once())
            ->method('getCurrentWeather')
            ->with($city)
            ->willReturn($apiData);

        $this->weatherCacheService
            ->expects($this->once())
            ->method('saveWeatherData')
            ->with($apiData)
            ->willReturn($freshWeatherData);

        // Act
        $result = $this->weatherService->getWeather($city);

        // Assert
        $this->assertEquals('Tokyo', $result['city']);
        $this->assertTrue($result['cached']);
        $this->assertFalse($result['stale']);
    }

    public function testSavesDataAfterApiCall(): void
    {
        // Arrange
        $city = 'Sydney';
        $apiData = [
            'city' => 'Sydney',
            'country' => 'Australia',
            'temperature' => 22.0,
            'condition' => 'Clear',
            'humidity' => 60,
            'wind_speed' => 15.0,
            'last_updated' => '2024-01-15 14:00'
        ];

        $weatherData = $this->createWeatherDataEntity($city);

        $this->weatherDataRepository
            ->expects($this->once())
            ->method('findRecentByCity')
            ->willReturn(null);

        $this->weatherApiClient
            ->expects($this->once())
            ->method('getCurrentWeather')
            ->willReturn($apiData);

        $this->weatherCacheService
            ->expects($this->once())
            ->method('saveWeatherData')
            ->with($apiData)
            ->willReturn($weatherData);

        // Act
        $result = $this->weatherService->getWeather($city);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('Sydney', $result['city']);
    }

    public function testForceRefreshBypassesCache(): void
    {
        // Arrange
        $city = 'Berlin';
        $apiData = [
            'city' => 'Berlin',
            'country' => 'Germany',
            'temperature' => 10.0,
            'condition' => 'Cloudy',
            'humidity' => 70,
            'wind_speed' => 20.0,
            'last_updated' => '2024-01-15 14:00'
        ];

        $weatherData = $this->createWeatherDataEntity($city);

        // Cache check should be skipped
        $this->weatherDataRepository
            ->expects($this->never())
            ->method('findRecentByCity');

        $this->weatherApiClient
            ->expects($this->once())
            ->method('getCurrentWeather')
            ->with($city)
            ->willReturn($apiData);

        $this->weatherCacheService
            ->expects($this->once())
            ->method('saveWeatherData')
            ->willReturn($weatherData);

        // Act
        $result = $this->weatherService->getWeather($city, true); // force refresh

        // Assert
        $this->assertEquals('Berlin', $result['city']);
    }

    public function testReturnsStaleDataWhenApiFails(): void
    {
        // Arrange
        $city = 'Moscow';
        $staleWeatherData = $this->createWeatherDataEntity($city);

        $this->weatherDataRepository
            ->expects($this->once())
            ->method('findRecentByCity')
            ->willReturn(null);

        $this->weatherApiClient
            ->expects($this->once())
            ->method('getCurrentWeather')
            ->willThrowException(new WeatherApiException('API Error', 503));

        // Should try to find any cached data (even stale)
        $this->weatherDataRepository
            ->expects($this->once())
            ->method('findByCity')
            ->with($city)
            ->willReturn($staleWeatherData);

        // Act
        $result = $this->weatherService->getWeather($city);

        // Assert
        $this->assertEquals('Moscow', $result['city']);
        $this->assertTrue($result['cached']);
        $this->assertTrue($result['stale']);
    }

    public function testThrowsExceptionWhenNoDataAvailable(): void
    {
        // Arrange
        $city = 'Unknown';

        $this->weatherDataRepository
            ->expects($this->once())
            ->method('findRecentByCity')
            ->willReturn(null);

        $this->weatherApiClient
            ->expects($this->once())
            ->method('getCurrentWeather')
            ->willThrowException(new WeatherApiException('API Error', 503));

        $this->weatherDataRepository
            ->expects($this->once())
            ->method('findByCity')
            ->willReturn(null);

        // Assert
        $this->expectException(WeatherApiException::class);

        // Act
        $this->weatherService->getWeather($city);
    }

    public function testGetCacheStats(): void
    {
        // Arrange
        $allWeatherData = [
            $this->createWeatherDataEntity('London'),
            $this->createWeatherDataEntity('Paris'),
            $this->createWeatherDataEntity('Tokyo')
        ];

        $this->weatherDataRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($allWeatherData);

        $this->weatherDataRepository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->createMockQueryBuilder(2)); // 2 fresh entries

        // Act
        $stats = $this->weatherService->getCacheStats();

        // Assert
        $this->assertEquals(3, $stats['total_cached_cities']);
        $this->assertEquals(2, $stats['fresh_cache_entries']);
        $this->assertEquals(1, $stats['stale_cache_entries']);
        $this->assertEquals(30, $stats['cache_max_age_minutes']);
    }

    private function createWeatherDataEntity(string $city): WeatherData
    {
        $weatherData = new WeatherData();
        $weatherData->setCity($city);
        $weatherData->setCountry('United Kingdom');
        $weatherData->setTemperature(15.5);
        $weatherData->setCondition('Partly cloudy');
        $weatherData->setHumidity(65);
        $weatherData->setWindSpeed(12.5);
        $weatherData->setLastUpdated(new \DateTime());
        $weatherData->setCreatedAt(new \DateTime());
        $weatherData->setApiLastUpdated('2024-01-15 14:00');

        return $weatherData;
    }

    private function createMockQueryBuilder(int $resultCount)
    {
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn(array_fill(0, $resultCount, new WeatherData()));

        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('where')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        return $queryBuilder;
    }
}