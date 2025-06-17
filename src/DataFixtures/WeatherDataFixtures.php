<?php

namespace App\DataFixtures;

use App\Entity\WeatherData;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class WeatherDataFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $weatherDataList = [
            [
                'city' => 'London',
                'country' => 'United Kingdom',
                'temperature' => 15.0,
                'condition' => 'Partly cloudy',
                'humidity' => 65,
                'wind_speed' => 12.5,
                'hours_ago' => 1,
            ],
            [
                'city' => 'Paris',
                'country' => 'France',
                'temperature' => 18.0,
                'condition' => 'Sunny',
                'humidity' => 45,
                'wind_speed' => 8.0,
                'hours_ago' => 2,
            ],
            [
                'city' => 'New York',
                'country' => 'United States',
                'temperature' => 22.0,
                'condition' => 'Clear',
                'humidity' => 55,
                'wind_speed' => 15.0,
                'hours_ago' => 0.5,
            ],
            [
                'city' => 'Tokyo',
                'country' => 'Japan',
                'temperature' => 25.0,
                'condition' => 'Humid',
                'humidity' => 75,
                'wind_speed' => 10.0,
                'hours_ago' => 0.75,
            ],
        ];

        foreach ($weatherDataList as $data) {
            $weatherData = new WeatherData();

            $weatherData->setCity($data['city']);
            $weatherData->setCountry($data['country']);
            $weatherData->setTemperature($data['temperature']);
            $weatherData->setCondition($data['condition']);
            $weatherData->setHumidity($data['humidity']);
            $weatherData->setWindSpeed($data['wind_speed']);

            // Set timestamps based on hours_ago
            $createdAt = new \DateTime();
            $createdAt->modify(sprintf('-%s hours', $data['hours_ago']));
            $weatherData->setCreatedAt($createdAt);

            $lastUpdated = clone $createdAt;
            $weatherData->setLastUpdated($lastUpdated);

            // API last updated should be a string format
            $weatherData->setApiLastUpdated($lastUpdated->format('Y-m-d H:i'));

            $manager->persist($weatherData);
        }

        $manager->flush();
    }
}
