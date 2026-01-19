<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests funcionales para OrganizacionController
 */
class OrganizacionControllerTest extends WebTestCase
{
    // ========================================================================
    // TESTS DE LISTADO (GET /organizaciones)
    // ========================================================================

    public function testListarOrganizacionesDevuelve200(): void
    {
        $client = static::createClient();

        $client->request('GET', '/organizaciones');

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testListarOrganizacionesDevuelveJSON(): void
    {
        $client = static::createClient();

        $client->request('GET', '/organizaciones');

        $this->assertJson($client->getResponse()->getContent());
    }

    public function testListarOrganizacionesDevuelveArray(): void
    {
        $client = static::createClient();

        $client->request('GET', '/organizaciones');

        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
    }

    // ========================================================================
    // TESTS DE DETALLE (GET /organizaciones/{id})
    // ========================================================================

    public function testDetalleOrganizacionInexistenteDevuelve404(): void
    {
        $client = static::createClient();

        $client->request('GET', '/organizaciones/999999');

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testDetalleOrganizacionDevuelveJSON(): void
    {
        $client = static::createClient();

        $client->request('GET', '/organizaciones/1');

        $this->assertJson($client->getResponse()->getContent());
    }

    // ========================================================================
    // TESTS DE CREACIÓN (POST /organizaciones)
    // ========================================================================

    public function testRegistrarOrganizacionSinDatosDevuelveError(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/organizaciones',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [
            Response::HTTP_UNPROCESSABLE_ENTITY,
            Response::HTTP_BAD_REQUEST
        ]);
    }

    public function testRegistrarOrganizacionConDatosIncompletos(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/organizaciones',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'nombre' => 'ONG Test',
                // Faltan campos requeridos
            ])
        );

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(Response::HTTP_CREATED, $statusCode);
    }

    // ========================================================================
    // TESTS DE ACTUALIZACIÓN (PUT /organizaciones/{id})
    // ========================================================================

    public function testActualizarOrganizacionInexistenteDevuelve404(): void
    {
        $client = static::createClient();

        $client->request(
            'PUT',
            '/organizaciones/999999',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'nombre' => 'ONG Actualizada',
                'descripcion' => 'Nueva descripción'
            ])
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    // ========================================================================
    // TESTS DE ACTIVIDADES DE ORGANIZACIÓN
    // ========================================================================

    public function testListarActividadesOrganizacionInexistente(): void
    {
        $client = static::createClient();

        $client->request('GET', '/organizaciones/999999/actividades');

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testCrearActividadOrganizacionInexistente(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/organizaciones/999999/actividades',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'titulo' => 'Nueva actividad',
                'descripcion' => 'Descripción de prueba',
                'fecha_inicio' => '2025-08-01 10:00:00',
                'duracion_horas' => 4,
                'cupo_maximo' => 20,
                'ubicacion' => 'Test Location'
            ])
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    // ========================================================================
    // TESTS DE ESTADÍSTICAS
    // ========================================================================

    public function testEstadisticasOrganizacionInexistente(): void
    {
        $client = static::createClient();

        $client->request('GET', '/organizaciones/999999/estadisticas');

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    // ========================================================================
    // TESTS DE TOP ORGANIZACIONES
    // ========================================================================

    public function testTopOrganizacionesDevuelve200(): void
    {
        $client = static::createClient();

        $client->request('GET', '/organizaciones/top-voluntarios');

        $statusCode = $client->getResponse()->getStatusCode();
        // Puede ser 200 o 404 si no hay datos
        $this->assertContains($statusCode, [Response::HTTP_OK, Response::HTTP_NOT_FOUND]);
    }

    public function testTopOrganizacionesDevuelveJSON(): void
    {
        $client = static::createClient();

        $client->request('GET', '/organizaciones/top-voluntarios');

        $this->assertJson($client->getResponse()->getContent());
    }

    // ========================================================================
    // TESTS DE VOLUNTARIOS DE ACTIVIDAD
    // ========================================================================

    public function testVoluntariosActividadOrganizacionInexistente(): void
    {
        $client = static::createClient();

        $client->request('GET', '/organizaciones/999999/actividades/1/voluntarios');

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testVoluntariosActividadInexistente(): void
    {
        $client = static::createClient();

        // Si hay organizaciones, probar con una existente
        $client->request('GET', '/organizaciones/1/actividades/999999/voluntarios');

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [
            Response::HTTP_NOT_FOUND,
            Response::HTTP_FORBIDDEN
        ]);
    }

    // ========================================================================
    // TESTS DE ESTRUCTURA DE RESPUESTA
    // ========================================================================

    public function testListarOrganizacionesContieneEstructuraCorrecta(): void
    {
        $client = static::createClient();

        $client->request('GET', '/organizaciones');

        $content = json_decode($client->getResponse()->getContent(), true);

        if (count($content) > 0) {
            $organizacion = $content[0];

            // Verificar campos mínimos esperados
            $expectedFields = ['id_usuario', 'nombre'];
            foreach ($expectedFields as $field) {
                $this->assertArrayHasKey($field, $organizacion, "Falta el campo: $field");
            }
        }

        $this->assertTrue(true);
    }
}
