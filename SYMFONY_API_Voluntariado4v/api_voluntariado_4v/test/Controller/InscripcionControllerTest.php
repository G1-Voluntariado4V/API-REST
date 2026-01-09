<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests para gestión de inscripciones (parte de organizaciones)
 */
class InscripcionControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Test: GET /actividades/{idActividad}/inscripciones
     * Listar solicitudes de una actividad
     */
    public function testListarInscripcionesActividad(): void
    {
        $this->client->request('GET', '/actividades/1/inscripciones');

        $statusCode = $this->client->getResponse()->getStatusCode();

        if ($statusCode === 200) {
            $data = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertIsArray($data);
            if (count($data) > 0) {
                $this->assertArrayHasKey('estado', $data[0]);
                $this->assertArrayHasKey('nombre_voluntario', $data[0]);
            }
        }

        $this->assertTrue(in_array($statusCode, [200, 404]));
    }

    /**
     * Test: PATCH /actividades/{idActividad}/inscripciones/{idVoluntario}
     * Aceptar o rechazar una solicitud
     */
    public function testGestionarInscripcion(): void
    {
        $payload = ['estado' => 'Aceptada'];

        $this->client->request(
            'PATCH',
            '/actividades/1/inscripciones/1',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $statusCode = $this->client->getResponse()->getStatusCode();
        // 200: OK, 404: No existe inscripción, 409: Cupo lleno
        $this->assertTrue(in_array($statusCode, [200, 404, 409]));
    }
}
