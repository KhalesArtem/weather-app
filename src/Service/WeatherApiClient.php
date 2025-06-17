<?php

namespace App\Service;

use App\Exception\WeatherApiException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

class WeatherApiClient
{
    private const TIMEOUT = 10;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $apiKey,
        private string $apiUrl
    ) {
        if (empty($this->apiKey)) {
            throw WeatherApiException::apiKeyMissing();
        }
    }

    /**
     * Get current weather data for a city
     *
     * @param string $city
     * @return array
     * @throws WeatherApiException
     */
    public function getCurrentWeather(string $city): array
    {
        $this->logger->info('Fetching weather data', ['city' => $city]);

        try {
            $response = $this->httpClient->request('GET', $this->apiUrl . '/current.json', [
                'query' => [
                    'key' => $this->apiKey,
                    'q' => $city,
                    'aqi' => 'no'
                ],
                'timeout' => self::TIMEOUT,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent();
            
            $this->logger->debug('Weather API response received', [
                'city' => $city,
                'status_code' => $statusCode
            ]);

            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Failed to decode JSON response', [
                    'city' => $city,
                    'response' => $content,
                    'json_error' => json_last_error_msg()
                ]);

                throw WeatherApiException::invalidResponse(
                    'Invalid JSON response',
                    ['response' => $content]
                );
            }

            return $this->transformResponse($data);

        } catch (HttpExceptionInterface $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $errorContent = $e->getResponse()->getContent(false);
            
            $this->logger->error('Weather API HTTP error', [
                'city' => $city,
                'status_code' => $statusCode,
                'error' => $errorContent,
                'exception' => $e->getMessage()
            ]);

            if ($statusCode === 404) {
                throw WeatherApiException::cityNotFound($city);
            }

            if ($statusCode === 429) {
                throw WeatherApiException::rateLimitExceeded(['city' => $city]);
            }

            throw WeatherApiException::apiRequestFailed(
                $e->getMessage(),
                [
                    'status_code' => $statusCode,
                    'response' => $errorContent
                ]
            );

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Weather API transport error', [
                'city' => $city,
                'exception' => $e->getMessage()
            ]);

            throw WeatherApiException::apiRequestFailed(
                'Network error: ' . $e->getMessage(),
                ['city' => $city]
            );
        }
    }

    /**
     * Transform API response to a normalized format
     *
     * @param array $data
     * @return array
     */
    private function transformResponse(array $data): array
    {
        if (!isset($data['location']) || !isset($data['current'])) {
            throw WeatherApiException::invalidResponse(
                'Missing required fields in response',
                ['response' => $data]
            );
        }

        return [
            'city' => $data['location']['name'],
            'country' => $data['location']['country'],
            'temperature' => $data['current']['temp_c'],
            'condition' => $data['current']['condition']['text'],
            'humidity' => $data['current']['humidity'],
            'wind_speed' => $data['current']['wind_kph'],
            'last_updated' => $data['current']['last_updated'],
            'local_time' => $data['location']['localtime'],
            'timezone' => $data['location']['tz_id'] ?? null,
            'icon' => $data['current']['condition']['icon'] ?? null,
        ];
    }
}