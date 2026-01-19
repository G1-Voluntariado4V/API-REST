<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests funcionales para VoluntarioController
 */
class VoluntarioControllerTest extends WebTestCase
{
    // ========================================================================
    // TESTS DE LISTADO (GET /voluntarios)
    // ========================================================================

    public function testListarVoluntariosDevuelve200(): void
    {
        $client = static::createClient();

        $client->request('GET', '/voluntarios');

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testListarVoluntariosDevuelveJSON(): void
    {
        $client = static::createClient();

        $client->request('GET', '/voluntarios');

        $this->assertJson($client->getResponse()->getContent());
    }

    public function testListarVoluntariosDevuelveArray(): void
    {
        $client = static::createClient();

        $client->request('GET', '/voluntarios');

        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
    }

    // ========================================================================
    // TESTS DE DETALLE (GET /voluntarios/{id})
    // ========================================================================

    public function testDetalleVoluntarioInexistenteDevuelve404(): void
    {
        $client = static::createClient();

        $client->request('GET', '/voluntarios/999999');

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testDetalleVoluntarioDevuelveJSON(): void
    {
        $client = static::createClient();

        $client->request('GET', '/voluntarios/1');

        $this->assertJson($client->getResponse()->getContent());
    }

    // ========================================================================
    // TESTS DE REGISTRO (POST /voluntarios)
    // ========================================================================

    public function testRegistrarVoluntarioSinDatosDevuelveError(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/voluntarios',
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

    public function testRegistrarVoluntarioConDatosIncompletos(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/voluntarios',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'nombre' => 'Test',
                // Faltan campos requeridos
            ])
        );

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(Response::HTTP_CREATED, $statusCode);
    }

    public function testRegistrarVoluntarioConCorreoInvalido(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/voluntarios',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'google_id' => 'test123',
                'correo' => 'correo-invalido', // Sin formato de email
                'nombre' => 'Test',
                'apellidos' => 'Usuario'
            ])
        );

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(Response::HTTP_CREATED, $statusCode);
    }

    // ========================================================================
    // TESTS DE ACTUALIZACIÓN (PUT /voluntarios/{id})
    // ========================================================================

    public function testActualizarVoluntarioInexistenteDevuelve404(): void
    {
        $client = static::createClient();

        $client->request(
            'PUT',
            '/voluntarios/999999',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-User-Id' => '999999'
            ],
            json_encode([
                'nombre' => 'Nombre actualizado',
                'apellidos' => 'Apellidos actualizados'
            ])
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    // ========================================================================
    // TESTS DE INSCRIPCIÓN (POST /voluntarios/{id}/actividades/{idActividad})
    // ========================================================================

    public function testInscribirseConVoluntarioInexistente(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/voluntarios/999999/actividades/1',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-User-Id' => '999999'
            ]
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testInscribirseConActividadInexistente(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/voluntarios/1/actividades/999999',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-User-Id' => '1'
            ]
        );

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [
            Response::HTTP_NOT_FOUND,
            Response::HTTP_FORBIDDEN
        ]);
    }

    // ========================================================================
    // TESTS DE HISTORIAL (GET /voluntarios/{id}/historial)
    // ========================================================================

    public function testHistorialVoluntarioInexistente(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/voluntarios/999999/historial',
            [],
            [],
            ['HTTP_X-User-Id' => '999999']
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    // ========================================================================
    // TESTS DE HORAS TOTALES (GET /voluntarios/{id}/horas)
    // ========================================================================

    public function testHorasTotalesVoluntarioInexistente(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/voluntarios/999999/horas',
            [],
            [],
            ['HTTP_X-User-Id' => '999999']
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    // ========================================================================
    // TESTS DE ESTRUCTURA DE RESPUESTA
    // ========================================================================

    public function testListarVoluntariosContieneEstructuraCorrecta(): void
    {
        $client = static::createClient();

        $client->request('GET', '/voluntarios');

        $content = json_decode($client->getResponse()->getContent(), true);

        if (count($content) > 0) {
            $voluntario = $content[0];

            // Verificar campos mínimos esperados
            $expectedFields = ['id_usuario', 'nombre', 'apellidos'];
            foreach ($expectedFields as $field) {
                $this->assertArrayHasKey($field, $voluntario, "Falta el campo: $field");
            }
        }

        $this->assertTrue(true);
    }
}
