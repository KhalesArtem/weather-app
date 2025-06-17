<?php

namespace App\Tests\Unit\Service;

use App\Exception\WeatherApiException;
use App\Service\WeatherApiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class WeatherApiClientTest extends TestCase
{
    private LoggerInterface $logger;
    private string $apiKey = 'test-api-key';
    private string $apiUrl = 'https://api.weatherapi.com/v1';

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testSuccessfulDataRetrieval(): void
    {
        $city = 'London';
        $expectedResponse = [
            'location' => [
                'name' => 'London',
                'country' => 'United Kingdom',
                'localtime' => '2024-01-15 14:30',
                'tz_id' => 'Europe/London'
            ],
            'current' => [
                'temp_c' => 15.5,
                'condition' => [
                    'text' => 'Partly cloudy',
                    'icon' => '//cdn.weatherapi.com/weather/64x64/day/116.png'
                ],
                'humidity' => 65,
                'wind_kph' => 12.5,
                'last_updated' => '2024-01-15 14:15'
            ]
        ];

        $mockResponse = new MockResponse(json_encode($expectedResponse));
        $httpClient = new MockHttpClient([$mockResponse]);

        $weatherApiClient = new WeatherApiClient(
            $httpClient,
            $this->logger,
            $this->apiKey,
            $this->apiUrl
        );

        $result = $weatherApiClient->getCurrentWeather($city);

        $this->assertEquals('London', $result['city']);
        $this->assertEquals('United Kingdom', $result['country']);
        $this->assertEquals(15.5, $result['temperature']);
        $this->assertEquals('Partly cloudy', $result['condition']);
        $this->assertEquals(65, $result['humidity']);
        $this->assertEquals(12.5, $result['wind_speed']);
        $this->assertEquals('2024-01-15 14:15', $result['last_updated']);
        $this->assertEquals('2024-01-15 14:30', $result['local_time']);
        $this->assertEquals('Europe/London', $result['timezone']);
        $this->assertEquals('//cdn.weatherapi.com/weather/64x64/day/116.png', $result['icon']);
    }

    public function testApiErrorCityNotFound(): void
    {
        $city = 'InvalidCity';
        $errorResponse = [
            'error' => [
                'code' => 1006,
                'message' => 'No matching location found.'
            ]
        ];

        $mockResponse = new MockResponse(
            json_encode($errorResponse),
            ['http_code' => 404]
        );
        $httpClient = new MockHttpClient([$mockResponse]);

        $weatherApiClient = new WeatherApiClient(
            $httpClient,
            $this->logger,
            $this->apiKey,
            $this->apiUrl
        );

        $this->expectException(WeatherApiException::class);
        $this->expectExceptionMessage('City "InvalidCity" not found');

        $weatherApiClient->getCurrentWeather($city);
    }

    public function testNetworkError(): void
    {
        $city = 'London';
        $exception = new class('Network error') extends \Exception implements TransportExceptionInterface {};

        $mockResponse = new MockResponse(
            '',
            ['error' => $exception]
        );
        $httpClient = new MockHttpClient([$mockResponse]);

        $weatherApiClient = new WeatherApiClient(
            $httpClient,
            $this->logger,
            $this->apiKey,
            $this->apiUrl
        );

        $this->expectException(WeatherApiException::class);
        $this->expectExceptionMessageMatches('/Weather API request failed: Network error:/');

        $weatherApiClient->getCurrentWeather($city);
    }

    public function testInvalidJson(): void
    {
        $city = 'London';
        $invalidJson = '{invalid json}';

        $mockResponse = new MockResponse($invalidJson);
        $httpClient = new MockHttpClient([$mockResponse]);

        $weatherApiClient = new WeatherApiClient(
            $httpClient,
            $this->logger,
            $this->apiKey,
            $this->apiUrl
        );

        $this->expectException(WeatherApiException::class);
        $this->expectExceptionMessage('Invalid response from Weather API: Invalid JSON response');

        // Act
        $weatherApiClient->getCurrentWeather($city);
    }

    public function testMissingRequiredFields(): void
    {
        // Arrange
        $city = 'London';
        $incompleteResponse = [
            'location' => [
                'name' => 'London'
            ]
            // Missing 'current' field
        ];

        $mockResponse = new MockResponse(json_encode($incompleteResponse));
        $httpClient = new MockHttpClient([$mockResponse]);

        $weatherApiClient = new WeatherApiClient(
            $httpClient,
            $this->logger,
            $this->apiKey,
            $this->apiUrl
        );

        // Assert
        $this->expectException(WeatherApiException::class);
        $this->expectExceptionMessage('Invalid response from Weather API: Missing required fields in response');

        // Act
        $weatherApiClient->getCurrentWeather($city);
    }

    public function testRateLimitExceeded(): void
    {
        // Arrange
        $city = 'London';
        $errorResponse = [
            'error' => [
                'code' => 2009,
                'message' => 'API key has exceeded calls per month quota.'
            ]
        ];

        $mockResponse = new MockResponse(
            json_encode($errorResponse),
            ['http_code' => 429]
        );
        $httpClient = new MockHttpClient([$mockResponse]);

        $weatherApiClient = new WeatherApiClient(
            $httpClient,
            $this->logger,
            $this->apiKey,
            $this->apiUrl
        );

        // Assert
        $this->expectException(WeatherApiException::class);
        $this->expectExceptionMessage('Weather API rate limit exceeded');

        // Act
        $weatherApiClient->getCurrentWeather($city);
    }

    public function testMissingApiKeyThrowsException(): void
    {
        // Assert
        $this->expectException(WeatherApiException::class);
        $this->expectExceptionMessage('Weather API key is not configured');

        // Act
        new WeatherApiClient(
            new MockHttpClient(),
            $this->logger,
            '', // Empty API key
            $this->apiUrl
        );
    }
}
