<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\WeatherApiException;
use App\Form\CitySearchType;
use App\Service\WeatherService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Annotation\Route;

#[Route(name: 'weather_')]
final class WeatherController extends AbstractController
{
    private const DEFAULT_CITY = 'London';
    private const CITY_PATTERN = '[a-zA-Z\s\-]+';

    public function __construct(
        private readonly WeatherService $weatherService,
        private readonly LoggerInterface $weatherLogger
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('weather_show', ['city' => self::DEFAULT_CITY]);
    }

    #[Route(
        '/weather/{city}',
        name: 'show',
        requirements: ['city' => self::CITY_PATTERN],
        methods: ['GET', 'POST']
    )]
    public function show(
        string $city,
        Request $request,
        #[MapQueryParameter] bool $refresh = false
    ): Response {
        if ($request->isMethod('POST')) {
            return $this->handleCitySearch($request);
        }

        try {
            $weatherData = $this->weatherService->getWeather($city, $refresh);
            $this->addCacheFlashMessage($weatherData);

            return $this->render('weather/show.html.twig', [
                'weather' => $weatherData,
                'city' => $city,
                'cache_stats' => $this->weatherService->getCacheStats(),
            ]);
        } catch (WeatherApiException $e) {
            return $this->handleWeatherApiException($e, $city);
        } catch (\Throwable $e) {
            return $this->handleUnexpectedException($e, $city);
        }
    }

    #[Route('/api/weather', name: 'api_index', methods: ['GET'], priority: 10)]
    public function apiIndex(): JsonResponse
    {
        return $this->json([
            'endpoints' => [
                'get_weather' => '/api/weather/{city}',
                'clear_cache' => '/api/weather/{city}/cache/clear',
                'cache_stats' => '/api/weather/cache/stats',
            ],
            'parameters' => [
                'city' => 'City name (letters, spaces, hyphens only)',
                'refresh' => 'Force refresh from API (optional, boolean)',
            ],
            'documentation' => 'https://github.com/your-repo/weather-app#api-endpoints',
        ]);
    }

    #[Route(
        '/api/weather/{city}',
        name: 'api_get',
        requirements: ['city' => self::CITY_PATTERN],
        methods: ['GET']
    )]
    public function apiGet(
        string $city,
        #[MapQueryParameter] bool $refresh = false
    ): JsonResponse {
        try {
            $weatherData = $this->weatherService->getWeather($city, $refresh);

            return $this->json([
                'success' => true,
                'data' => $weatherData,
            ]);
        } catch (WeatherApiException $e) {
            return $this->handleApiWeatherException($e, $city);
        } catch (\Throwable $e) {
            return $this->handleApiUnexpectedException($e, $city);
        }
    }

    #[Route(
        '/api/weather/{city}/cache/clear',
        name: 'api_cache_clear',
        requirements: ['city' => self::CITY_PATTERN],
        methods: ['POST', 'DELETE']
    )]
    public function apiCacheClear(string $city): JsonResponse
    {
        try {
            $this->weatherService->clearCache($city);

            $this->weatherLogger->info('Cache cleared via API', ['city' => $city]);

            return $this->json([
                'success' => true,
                'message' => sprintf('Cache cleared for city: %s', $city),
            ]);
        } catch (\Throwable $e) {
            $this->weatherLogger->error('Error clearing cache via API', [
                'city' => $city,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to clear cache',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/weather/cache/stats', name: 'api_cache_stats', methods: ['GET'])]
    public function apiCacheStats(): JsonResponse
    {
        try {
            return $this->json([
                'success' => true,
                'data' => $this->weatherService->getCacheStats(),
            ]);
        } catch (\Throwable $e) {
            $this->weatherLogger->error('Error getting cache stats', [
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to get cache statistics',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function handleCitySearch(Request $request): Response
    {
        $searchCity = $request->request->get('city');

        if (!$searchCity) {
            $this->addFlash('warning', 'Please enter a city name');
            return $this->redirectToRoute('weather_show', ['city' => self::DEFAULT_CITY]);
        }

        return $this->redirectToRoute('weather_show', ['city' => trim($searchCity)]);
    }

    private function addCacheFlashMessage(array $weatherData): void
    {
        if ($weatherData['cached'] && !$weatherData['stale']) {
            $this->addFlash('info', sprintf(
                'Data from cache (updated %d minutes ago)',
                $weatherData['cache_age_minutes']
            ));
        } elseif ($weatherData['stale']) {
            $this->addFlash('warning', 'Showing stale data due to API unavailability');
        } else {
            $this->addFlash('success', 'Fresh data from Weather API');
        }
    }

    private function handleWeatherApiException(WeatherApiException $e, string $city): Response
    {
        $this->weatherLogger->error('Weather API error in controller', [
            'city' => $city,
            'error' => $e->getMessage(),
            'context' => $e->getContext(),
        ]);

        $this->addFlash('error', $e->getMessage());

        return $this->render('weather/error.html.twig', [
            'city' => $city,
            'error' => $e->getMessage(),
            'status_code' => $e->getStatusCode(),
        ], new Response('', $e->getStatusCode()));
    }

    private function handleUnexpectedException(\Throwable $e, string $city): Response
    {
        $this->weatherLogger->error('Unexpected error in weather controller', [
            'city' => $city,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $this->addFlash('error', 'An unexpected error occurred. Please try again later.');

        return $this->render('weather/error.html.twig', [
            'city' => $city,
            'error' => 'An unexpected error occurred',
            'status_code' => Response::HTTP_INTERNAL_SERVER_ERROR,
        ], new Response('', Response::HTTP_INTERNAL_SERVER_ERROR));
    }

    private function handleApiWeatherException(WeatherApiException $e, string $city): JsonResponse
    {
        $this->weatherLogger->error('Weather API error in API endpoint', [
            'city' => $city,
            'error' => $e->getMessage(),
            'context' => $e->getContext(),
        ]);

        return $this->json([
            'success' => false,
            'error' => $e->getMessage(),
            'context' => $e->getContext(),
        ], $e->getStatusCode());
    }

    private function handleApiUnexpectedException(\Throwable $e, string $city): JsonResponse
    {
        $this->weatherLogger->error('Unexpected error in weather API endpoint', [
            'city' => $city,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return $this->json([
            'success' => false,
            'error' => 'An unexpected error occurred',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
