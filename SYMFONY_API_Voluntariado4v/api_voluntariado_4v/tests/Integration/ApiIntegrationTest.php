<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests de integración que verifican el flujo completo de la API
 */
class ApiIntegrationTest extends WebTestCase
{
    // ========================================================================
    // TESTS DE DISPONIBILIDAD DE ENDPOINTS
    // ========================================================================

    /**
     * @dataProvider endpointsProvider
     */
    public function testEndpointsDisponibles(string $method, string $url, array $validCodes): void
    {
        $client = static::createClient();

        $client->request($method, $url);

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains(
            $statusCode,
            $validCodes,
            "Endpoint $method $url devolvió $statusCode, esperado uno de: " . implode(', ', $validCodes)
        );
    }

    public static function endpointsProvider(): array
    {
        return [
            'GET actividades' => ['GET', '/actividades', [200]],
            'GET voluntarios' => ['GET', '/voluntarios', [200]],
            'GET organizaciones' => ['GET', '/organizaciones', [200]],
            'GET top organizaciones' => ['GET', '/organizaciones/top-voluntarios', [200, 404]],
            'GET catalogo cursos' => ['GET', '/catalogo/cursos', [200]],
            'GET catalogo idiomas' => ['GET', '/catalogo/idiomas', [200]],
            'GET catalogo preferencias' => ['GET', '/catalogo/preferencias', [200]],
        ];
    }

    // ========================================================================
    // TESTS DE FORMATO DE RESPUESTA
    // ========================================================================

    /**
     * @dataProvider jsonEndpointsProvider
     */
    public function testRespuestasEnFormatoJSON(string $url): void
    {
        $client = static::createClient();

        $client->request('GET', $url);

        $this->assertJson($client->getResponse()->getContent());
    }

    public static function jsonEndpointsProvider(): array
    {
        return [
            'actividades' => ['/actividades'],
            'voluntarios' => ['/voluntarios'],
            'organizaciones' => ['/organizaciones'],
        ];
    }

    // ========================================================================
    // TESTS DE CORS Y HEADERS
    // ========================================================================

    public function testRespuestasTienenContentTypeJSON(): void
    {
        $client = static::createClient();

        $client->request('GET', '/actividades');

        $contentType = $client->getResponse()->headers->get('Content-Type');
        $this->assertStringContainsString('application/json', $contentType);
    }

    // ========================================================================
    // TESTS DE VALIDACIÓN DE ENTRADA
    // ========================================================================

    public function testPOSTConJSONMalFormadoDevuelveError(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"json_mal_formado":' // JSON incompleto
        );

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_UNPROCESSABLE_ENTITY
        ]);
    }

    // ========================================================================
    // TESTS DE RECURSOS NO ENCONTRADOS
    // ========================================================================

    /**
     * @dataProvider recurso404Provider
     */
    public function testRecursosInexistentesDevuelven404(string $url): void
    {
        $client = static::createClient();

        $client->request('GET', $url);

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public static function recurso404Provider(): array
    {
        return [
            'actividad inexistente' => ['/actividades/999999'],
            'voluntario inexistente' => ['/voluntarios/999999'],
            'organizacion inexistente' => ['/organizaciones/999999'],
        ];
    }

    // ========================================================================
    // TESTS DE MÉTODOS NO PERMITIDOS
    // ========================================================================

    public function testMetodosNoPermitidosEnListados(): void
    {
        $client = static::createClient();

        // PATCH no debería estar permitido en /actividades
        $client->request('PATCH', '/actividades');
        $this->assertEquals(Response::HTTP_METHOD_NOT_ALLOWED, $client->getResponse()->getStatusCode());

        // PUT no debería estar permitido en /organizaciones (sin ID)
        $client->request('PUT', '/organizaciones');
        $this->assertEquals(Response::HTTP_METHOD_NOT_ALLOWED, $client->getResponse()->getStatusCode());
    }

    // ========================================================================
    // TESTS DE AUTENTICACIÓN
    // ========================================================================

    public function testEndpointsProtegidosRequierenAutenticacion(): void
    {
        $client = static::createClient();

        // Dashboard de coordinador requiere autenticación
        $client->request('GET', '/coordinadores/dashboard');

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [
            Response::HTTP_FORBIDDEN,
            Response::HTTP_UNAUTHORIZED
        ]);
    }

    // ========================================================================
    // TESTS DE PAGINACIÓN (si aplica)
    // ========================================================================

    public function testPaginacionEnListados(): void
    {
        $client = static::createClient();

        // Verificar que se aceptan parámetros de paginación
        $client->request('GET', '/actividades?page=1&limit=5');

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
    }

    // ========================================================================
    // TESTS DE FILTRADO
    // ========================================================================

    public function testFiltradoEnActividades(): void
    {
        $client = static::createClient();

        // Filtrar por estado
        $client->request('GET', '/actividades?estado=Publicada');
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // Filtrar por tipo
        $client->request('GET', '/actividades?tipo=1');
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    // ========================================================================
    // TESTS DE CONSISTENCIA DE DATOS
    // ========================================================================

    public function testArraysNoSonNulosEnRespuestas(): void
    {
        $client = static::createClient();

        $client->request('GET', '/actividades');
        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertNotNull($content);
        $this->assertIsArray($content);
    }

    // ========================================================================
    // TESTS DE RENDIMIENTO BÁSICO
    // ========================================================================

    public function testRespuestaEnTiempoRazonable(): void
    {
        $client = static::createClient();

        $startTime = microtime(true);
        $client->request('GET', '/actividades');
        $endTime = microtime(true);

        $responseTime = $endTime - $startTime;

        // La respuesta debe llegar en menos de 5 segundos
        $this->assertLessThan(5, $responseTime, 'La respuesta tardó demasiado');
    }
}
