<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class WeatherApiException extends HttpException
{
    private array $context;

    public function __construct(
        string $message,
        int $statusCode = 500,
        array $context = [],
        ?\Throwable $previous = null,
        array $headers = []
    ) {
        $this->context = $context;
        parent::__construct($statusCode, $message, $previous, $headers);
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public static function apiKeyMissing(): self
    {
        return new self(
            'Weather API key is not configured',
            500,
            ['error_type' => 'configuration']
        );
    }

    public static function apiRequestFailed(string $message, array $context = []): self
    {
        return new self(
            sprintf('Weather API request failed: %s', $message),
            503,
            array_merge(['error_type' => 'api_request'], $context)
        );
    }

    public static function invalidResponse(string $reason, array $context = []): self
    {
        return new self(
            sprintf('Invalid response from Weather API: %s', $reason),
            502,
            array_merge(['error_type' => 'invalid_response'], $context)
        );
    }

    public static function cityNotFound(string $city): self
    {
        return new self(
            sprintf('City "%s" not found', $city),
            404,
            ['error_type' => 'city_not_found', 'city' => $city]
        );
    }

    public static function rateLimitExceeded(array $context = []): self
    {
        return new self(
            'Weather API rate limit exceeded',
            429,
            array_merge(['error_type' => 'rate_limit'], $context)
        );
    }
}