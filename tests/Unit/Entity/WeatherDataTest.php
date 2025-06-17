<?php

namespace App\Tests\Unit\Entity;

use App\Entity\WeatherData;
use DateTime;
use PHPUnit\Framework\TestCase;

class WeatherDataTest extends TestCase
{
    private WeatherData $weatherData;

    protected function setUp(): void
    {
        $this->weatherData = new WeatherData();
    }

    public function testGetterAndSetterForId(): void
    {
        $this->assertNull($this->weatherData->getId());
    }

    public function testGetterAndSetterForCity(): void
    {
        $this->weatherData->setCity('London');

        $this->assertEquals('London', $this->weatherData->getCity());
    }

    public function testGetterAndSetterForCountry(): void
    {
        $this->weatherData->setCountry('United Kingdom');

        $this->assertEquals('United Kingdom', $this->weatherData->getCountry());
    }

    public function testGetterAndSetterForTemperature(): void
    {
        $this->weatherData->setTemperature(15.5);

        $this->assertEquals(15.5, $this->weatherData->getTemperature());
    }

    public function testGetterAndSetterForCondition(): void
    {
        $this->weatherData->setCondition('Partly cloudy');

        $this->assertEquals('Partly cloudy', $this->weatherData->getCondition());
    }

    public function testGetterAndSetterForHumidity(): void
    {
        $this->weatherData->setHumidity(65);

        $this->assertEquals(65, $this->weatherData->getHumidity());
    }

    public function testGetterAndSetterForWindSpeed(): void
    {
        $this->weatherData->setWindSpeed(12.5);

        $this->assertEquals(12.5, $this->weatherData->getWindSpeed());
    }

    public function testGetterAndSetterForLastUpdated(): void
    {
        $date = new DateTime('2024-01-15 14:00:00');

        $this->weatherData->setLastUpdated($date);

        $this->assertEquals($date, $this->weatherData->getLastUpdated());
    }

    public function testGetterAndSetterForCreatedAt(): void
    {
        $date = new \DateTime('2024-01-15 12:00:00');

        $this->weatherData->setCreatedAt($date);

        $this->assertEquals($date, $this->weatherData->getCreatedAt());
    }

    public function testGetterAndSetterForApiLastUpdated(): void
    {
        $this->weatherData->setApiLastUpdated('2024-01-15 14:00');

        $this->assertEquals('2024-01-15 14:00', $this->weatherData->getApiLastUpdated());
    }

    public function testCreatedAtIsSetAutomaticallyOnPrePersist(): void
    {
        $weatherData = new WeatherData();
        $this->assertNull($weatherData->getCreatedAt());

        $weatherData->prePersist();

        $this->assertNotNull($weatherData->getCreatedAt());
        $this->assertInstanceOf(\DateTime::class, $weatherData->getCreatedAt());

        $now = new \DateTime();
        $diff = $now->getTimestamp() - $weatherData->getCreatedAt()->getTimestamp();
        $this->assertLessThanOrEqual(60, $diff);
    }

    public function testPrePersistDoesNotOverwriteExistingCreatedAt(): void
    {
        $originalDate = new \DateTime('2024-01-01 10:00:00');
        $this->weatherData->setCreatedAt($originalDate);

        $this->weatherData->prePersist();

        $this->assertEquals($originalDate, $this->weatherData->getCreatedAt());
    }

    public function testFluentInterface(): void
    {
        $result = $this->weatherData
            ->setCity('Tokyo')
            ->setCountry('Japan')
            ->setTemperature(25.0)
            ->setCondition('Humid')
            ->setHumidity(75)
            ->setWindSpeed(10.0)
            ->setLastUpdated(new \DateTime())
            ->setCreatedAt(new \DateTime())
            ->setApiLastUpdated('2024-01-15 22:00');

        $this->assertSame($this->weatherData, $result);
        $this->assertEquals('Tokyo', $this->weatherData->getCity());
        $this->assertEquals('Japan', $this->weatherData->getCountry());
        $this->assertEquals(25.0, $this->weatherData->getTemperature());
        $this->assertEquals('Humid', $this->weatherData->getCondition());
        $this->assertEquals(75, $this->weatherData->getHumidity());
        $this->assertEquals(10.0, $this->weatherData->getWindSpeed());
        $this->assertEquals('2024-01-15 22:00', $this->weatherData->getApiLastUpdated());
    }

    public function testCompleteEntityCreation(): void
    {
        $now = new \DateTime();
        $this->weatherData
            ->setCity('Sydney')
            ->setCountry('Australia')
            ->setTemperature(22.0)
            ->setCondition('Clear')
            ->setHumidity(60)
            ->setWindSpeed(15.0)
            ->setLastUpdated($now)
            ->setApiLastUpdated($now->format('Y-m-d H:i'));

        $this->weatherData->prePersist();

        $this->assertEquals('Sydney', $this->weatherData->getCity());
        $this->assertEquals('Australia', $this->weatherData->getCountry());
        $this->assertEquals(22.0, $this->weatherData->getTemperature());
        $this->assertEquals('Clear', $this->weatherData->getCondition());
        $this->assertEquals(60, $this->weatherData->getHumidity());
        $this->assertEquals(15.0, $this->weatherData->getWindSpeed());
        $this->assertEquals($now, $this->weatherData->getLastUpdated());
        $this->assertNotNull($this->weatherData->getCreatedAt());
        $this->assertEquals($now->format('Y-m-d H:i'), $this->weatherData->getApiLastUpdated());
    }

    public function testNullableProperties(): void
    {
        $weatherData = new WeatherData();

        $this->assertNull($weatherData->getId());
        $this->assertNull($weatherData->getCity());
        $this->assertNull($weatherData->getCountry());
        $this->assertNull($weatherData->getTemperature());
        $this->assertNull($weatherData->getCondition());
        $this->assertNull($weatherData->getHumidity());
        $this->assertNull($weatherData->getWindSpeed());
        $this->assertNull($weatherData->getLastUpdated());
        $this->assertNull($weatherData->getCreatedAt());
        $this->assertNull($weatherData->getApiLastUpdated());
    }
}
