<?php

namespace App\Tests\Unit\Service;

use App\Entity\WeatherData;
use App\Repository\WeatherDataRepository;
use App\Service\WeatherCacheService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WeatherCacheServiceTest extends TestCase
{
    private WeatherDataRepository $weatherDataRepository;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private WeatherCacheService $weatherCacheService;

    protected function setUp(): void
    {
        $this->weatherDataRepository = $this->createMock(WeatherDataRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->weatherCacheService = new WeatherCacheService(
            $this->weatherDataRepository,
            $this->entityManager,
            $this->logger
        );
    }

    public function testIsDataFreshWithFreshData(): void
    {
        // Arrange
        $weatherData = new WeatherData();
        $weatherData->setLastUpdated(new \DateTime('-10 minutes'));

        // Act
        $isFresh = $this->weatherCacheService->isDataFresh($weatherData, 30);

        // Assert
        $this->assertTrue($isFresh);
    }

    public function testIsDataFreshWithStaleData(): void
    {
        // Arrange
        $weatherData = new WeatherData();
        $weatherData->setLastUpdated(new \DateTime('-45 minutes'));

        // Act
        $isFresh = $this->weatherCacheService->isDataFresh($weatherData, 30);

        // Assert
        $this->assertFalse($isFresh);
    }

    public function testIsDataFreshWithExactThreshold(): void
    {
        // Arrange
        $weatherData = new WeatherData();
        $weatherData->setLastUpdated(new \DateTime('-30 minutes'));

        // Act
        $isFresh = $this->weatherCacheService->isDataFresh($weatherData, 30);

        // Assert
        $this->assertTrue($isFresh); // Should be inclusive
    }

    public function testIsDataFreshWithNullLastUpdated(): void
    {
        // Arrange
        $weatherData = new WeatherData();
        // Don't set lastUpdated, it will be null

        // Act
        $isFresh = $this->weatherCacheService->isDataFresh($weatherData, 30);

        // Assert
        $this->assertFalse($isFresh);
    }

    public function testSaveWeatherDataCreatesNewEntity(): void
    {
        // Arrange
        $data = [
            'city' => 'London',
            'country' => 'United Kingdom',
            'temperature' => 15.5,
            'condition' => 'Partly cloudy',
            'humidity' => 65,
            'wind_speed' => 12.5,
            'last_updated' => '2024-01-15 14:00'
        ];

        $this->weatherDataRepository
            ->expects($this->once())
            ->method('findByCity')
            ->with('London')
            ->willReturn(null); // No existing data

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(WeatherData::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $result = $this->weatherCacheService->saveWeatherData($data);

        // Assert
        $this->assertInstanceOf(WeatherData::class, $result);
        $this->assertEquals('London', $result->getCity());
        $this->assertEquals('United Kingdom', $result->getCountry());
        $this->assertEquals(15.5, $result->getTemperature());
        $this->assertEquals('Partly cloudy', $result->getCondition());
        $this->assertEquals(65, $result->getHumidity());
        $this->assertEquals(12.5, $result->getWindSpeed());
        $this->assertEquals('2024-01-15 14:00', $result->getApiLastUpdated());
        $this->assertNotNull($result->getCreatedAt());
        $this->assertNotNull($result->getLastUpdated());
    }

    public function testSaveWeatherDataUpdatesExistingEntity(): void
    {
        // Arrange
        $existingData = new WeatherData();
        $existingData->setCity('Paris');
        $existingData->setCountry('France');
        $existingData->setTemperature(10.0);
        $existingData->setCondition('Cloudy');
        $existingData->setHumidity(70);
        $existingData->setWindSpeed(15.0);
        $existingData->setCreatedAt(new \DateTime('-1 day'));

        $newData = [
            'city' => 'Paris',
            'country' => 'France',
            'temperature' => 18.0,
            'condition' => 'Sunny',
            'humidity' => 45,
            'wind_speed' => 8.0,
            'last_updated' => '2024-01-15 15:00'
        ];

        $this->weatherDataRepository
            ->expects($this->once())
            ->method('findByCity')
            ->with('Paris')
            ->willReturn($existingData);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($existingData);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $result = $this->weatherCacheService->saveWeatherData($newData);

        // Assert
        $this->assertSame($existingData, $result);
        $this->assertEquals('Paris', $result->getCity());
        $this->assertEquals(18.0, $result->getTemperature());
        $this->assertEquals('Sunny', $result->getCondition());
        $this->assertEquals(45, $result->getHumidity());
        $this->assertEquals(8.0, $result->getWindSpeed());
        $this->assertNotNull($result->getLastUpdated());
        $this->assertNotEquals($result->getCreatedAt(), $result->getLastUpdated());
    }

    public function testClearCacheForCityRemovesRecord(): void
    {
        // Arrange
        $city = 'Tokyo';
        $weatherData = new WeatherData();
        $weatherData->setCity($city);

        $this->weatherDataRepository
            ->expects($this->once())
            ->method('findByCity')
            ->with($city)
            ->willReturn($weatherData);

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($weatherData);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $this->weatherCacheService->clearCacheForCity($city);
    }

    public function testClearCacheForCityWithNoRecord(): void
    {
        // Arrange
        $city = 'NonExistentCity';

        $this->weatherDataRepository
            ->expects($this->once())
            ->method('findByCity')
            ->with($city)
            ->willReturn(null);

        $this->entityManager
            ->expects($this->never())
            ->method('remove');

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        // Act
        $this->weatherCacheService->clearCacheForCity($city);
    }

    public function testClearOldCache(): void
    {
        // Arrange
        $daysToKeep = 7;
        $expectedDeletedCount = 5;

        $this->weatherDataRepository
            ->expects($this->once())
            ->method('cleanOldRecords')
            ->with($daysToKeep)
            ->willReturn($expectedDeletedCount);

        // Act
        $deletedCount = $this->weatherCacheService->clearOldCache($daysToKeep);

        // Assert
        $this->assertEquals($expectedDeletedCount, $deletedCount);
    }

    public function testIsDataFreshWithVariousAges(): void
    {
        $testCases = [
            ['age_minutes' => 0, 'max_age' => 30, 'expected' => true],
            ['age_minutes' => 15, 'max_age' => 30, 'expected' => true],
            ['age_minutes' => 30, 'max_age' => 30, 'expected' => true],
            ['age_minutes' => 31, 'max_age' => 30, 'expected' => false],
            ['age_minutes' => 60, 'max_age' => 30, 'expected' => false],
            ['age_minutes' => 1, 'max_age' => 60, 'expected' => true],
            ['age_minutes' => 59, 'max_age' => 60, 'expected' => true],
            ['age_minutes' => 61, 'max_age' => 60, 'expected' => false],
        ];

        foreach ($testCases as $testCase) {
            $weatherData = new WeatherData();
            $weatherData->setLastUpdated(
                new \DateTime(sprintf('-%d minutes', $testCase['age_minutes']))
            );

            $isFresh = $this->weatherCacheService->isDataFresh(
                $weatherData,
                $testCase['max_age']
            );

            $this->assertEquals(
                $testCase['expected'],
                $isFresh,
                sprintf(
                    'Failed for age %d minutes with max age %d',
                    $testCase['age_minutes'],
                    $testCase['max_age']
                )
            );
        }
    }
}