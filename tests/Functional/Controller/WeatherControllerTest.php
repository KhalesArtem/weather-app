<?php

namespace App\Tests\Functional\Controller;

use App\Entity\WeatherData;
use App\Repository\WeatherDataRepository;
use App\Service\WeatherApiClient;
use App\Exception\WeatherApiException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class WeatherControllerTest extends WebTestCase
{
    private $client;
    private WeatherDataRepository $weatherDataRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->weatherDataRepository = static::getContainer()->get(WeatherDataRepository::class);

        $this->mockWeatherApiClient();

        $this->loadTestFixtures();
    }

    /**
     * Test homepage redirects to London weather
     */
    public function testHomepageRedirectsToLondon(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseRedirects('/weather/London');
    }

    /**
     * Test weather page with fixture data (cache hit)
     */
    public function testWeatherPageWithCacheHit(): void
    {
        $crawler = $this->client->request('GET', '/weather/London');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Weather in London, United Kingdom');

        $this->assertSelectorTextContains('.display-4', '15°C');
        $this->assertSelectorTextContains('.lead', 'Partly cloudy');

        $this->assertSelectorTextContains('.card-footer small', 'Data from cache');

        $this->assertSelectorExists('.alert-info');
        $this->assertSelectorTextContains('.alert', 'Data from cache');
    }

    /**
     * Test weather page with non-fixture city (would trigger API call)
     */
    public function testWeatherPageWithCacheMiss(): void
    {
        // Barcelona is not in fixtures
        $crawler = $this->client->request('GET', '/weather/Barcelona');

        // Should get an error since API is mocked to throw exception with 401
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertSelectorTextContains('.alert-danger', 'API key is invalid');
    }

    /**
     * Test weather page with force refresh
     */
    public function testWeatherPageWithForceRefresh(): void
    {
        $crawler = $this->client->request('GET', '/weather/London?refresh=true');

        // Should get an error since API is mocked to throw exception with 401
        // When forcing refresh, the stale cache data will be returned with the original status
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Weather in London, United Kingdom');
        $this->assertSelectorTextContains('.badge', 'Stale');
    }

    /**
     * Test city search form submission
     */
    public function testCitySearchFormSubmission(): void
    {
        $crawler = $this->client->request('GET', '/weather/London');

        $form = $crawler->selectButton('Search')->form();
        $form['city'] = 'Paris';

        $this->client->submit($form);

        $this->assertResponseRedirects('/weather/Paris');
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Weather in Paris, France');
    }

    /**
     * Test API endpoint with cache hit
     */
    public function testApiWeatherEndpointWithCacheHit(): void
    {
        $this->client->request('GET', '/api/weather/Tokyo');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('Tokyo', $data['data']['city']);
        $this->assertEquals('Japan', $data['data']['country']);
        $this->assertEquals(25.0, $data['data']['temperature']);
        $this->assertEquals('Humid', $data['data']['condition']);
        $this->assertTrue($data['data']['cached']);
        $this->assertFalse($data['data']['stale']);
    }

    /**
     * Test API endpoint with non-fixture city
     */
    public function testApiWeatherEndpointWithCacheMiss(): void
    {
        $this->client->request('GET', '/api/weather/Berlin');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('API key is invalid', $data['error']);
    }

    /**
     * Test cache clear endpoint
     */
    public function testCacheClearEndpoint(): void
    {
        $weatherData = $this->weatherDataRepository->findByCity('London');
        $this->assertNotNull($weatherData);

        $this->client->request('POST', '/api/weather/London/cache/clear');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Cache cleared for city: London', $data['message']);

        // Verify London no longer exists in cache
        $weatherData = $this->weatherDataRepository->findByCity('London');
        $this->assertNull($weatherData);
    }

    /**
     * Test cache clear endpoint with non-existent city
     */
    public function testCacheClearEndpointWithNonExistentCity(): void
    {
        $this->client->request('POST', '/api/weather/NonExistentCity/cache/clear');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Cache cleared for city: NonExistentCity', $data['message']);
    }

    /**
     * Test cache statistics endpoint
     */
    public function testCacheStatsEndpoint(): void
    {
        $this->client->request('GET', '/api/weather/cache/stats');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('total_cached_cities', $data['data']);
        $this->assertArrayHasKey('fresh_cache_entries', $data['data']);
        $this->assertArrayHasKey('stale_cache_entries', $data['data']);
        $this->assertArrayHasKey('cache_max_age_minutes', $data['data']);

        $this->assertEquals(5, $data['data']['total_cached_cities']);
    }

    /**
     * Test invalid city parameter
     */
    public function testInvalidCityParameter(): void
    {
        $this->client->request('GET', '/weather/City123!@#');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Test error page shows suggestions
     */
    public function testErrorPageShowsSuggestions(): void
    {
        $crawler = $this->client->request('GET', '/weather/FakeCity');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertSelectorExists('.list-group');

        $responseContent = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('London, United Kingdom', $responseContent);
        $this->assertStringContainsString('New York, United States', $responseContent);
        $this->assertStringContainsString('Tokyo, Japan', $responseContent);
    }

    /**
     * Test weather data display elements
     */
    public function testWeatherDataDisplayElements(): void
    {
        $crawler = $this->client->request('GET', '/weather/Paris');

        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('h3.display-4'); // Temperature
        $this->assertSelectorExists('p.lead'); // Condition

        $this->assertSelectorTextContains('.list-unstyled', 'Humidity:');
        $this->assertSelectorTextContains('.list-unstyled', 'Wind Speed:');
        $this->assertSelectorTextContains('.list-unstyled', 'Last Updated:');
        $this->assertSelectorTextContains('.list-unstyled', 'API Last Updated:');

        // Check cache statistics section exists (among multiple card headers)
        $this->assertSelectorExists('.card-header');
        $this->assertSelectorExists('.row.text-center');

        // Verify the page contains the expected sections
        $responseContent = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Cache Statistics', $responseContent);
        $this->assertStringContainsString('API Endpoints', $responseContent);
    }

    /**
     * Test API endpoints section on weather page
     */
    public function testApiEndpointsSectionDisplay(): void
    {
        $crawler = $this->client->request('GET', '/weather/Moscow');

        $this->assertResponseIsSuccessful();

        $responseContent = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('API Endpoints', $responseContent);

        $this->assertStringContainsString('/api/weather/Moscow', $responseContent);
        $this->assertStringContainsString('/api/weather/Moscow/cache/clear', $responseContent);
        $this->assertStringContainsString('/api/weather/cache/stats', $responseContent);
    }

    /**
     * Load test fixtures
     */
    private function loadTestFixtures(): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();

        // Clear existing data
        $em->createQuery('DELETE FROM App\Entity\WeatherData')->execute();

        $cities = [
            ['city' => 'London', 'country' => 'United Kingdom', 'temp' => 15.0, 'condition' => 'Partly cloudy'],
            ['city' => 'Paris', 'country' => 'France', 'temp' => 18.0, 'condition' => 'Sunny'],
            ['city' => 'New York', 'country' => 'United States', 'temp' => 22.0, 'condition' => 'Clear'],
            ['city' => 'Moscow', 'country' => 'Russia', 'temp' => 5.0, 'condition' => 'Snowing'],
            ['city' => 'Tokyo', 'country' => 'Japan', 'temp' => 25.0, 'condition' => 'Humid'],
        ];

        $now = new \DateTime();

        foreach ($cities as $data) {
            $weatherData = new WeatherData();
            $weatherData->setCity($data['city']);
            $weatherData->setCountry($data['country']);
            $weatherData->setTemperature($data['temp']);
            $weatherData->setCondition($data['condition']);
            $weatherData->setHumidity(60);
            $weatherData->setWindSpeed(10.0);
            $weatherData->setLastUpdated(clone $now);
            $weatherData->setCreatedAt(clone $now);
            $weatherData->setApiLastUpdated($now->format('Y-m-d H:i'));

            $em->persist($weatherData);
        }

        $em->flush();
        $em->clear(); // Clear entity manager to ensure fresh reads
    }

    /**
     * Mock WeatherApiClient to avoid real API calls
     */
    private function mockWeatherApiClient(): void
    {
        $weatherApiClientMock = $this->createMock(WeatherApiClient::class);

        $weatherApiClientMock->method('getCurrentWeather')
            ->willThrowException(new WeatherApiException('API key is invalid.', 401));

        static::getContainer()->set(WeatherApiClient::class, $weatherApiClientMock);
    }
}
