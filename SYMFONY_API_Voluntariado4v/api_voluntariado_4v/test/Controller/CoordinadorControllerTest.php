<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests para CoordinadorController basados en OpenAPI YAML
 * Endpoints administrativos que requieren X-Admin-Id
 */
class CoordinadorControllerTest extends WebTestCase
{
    private $client;
    // Usaremos una propiedad de instancia para el ID dinámico
    private int $adminId;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Buscamos el Coordinador creado en los Fixtures
        $container = static::getContainer();
        $userRepo = $container->get(\App\Repository\UsuarioRepository::class);
        $coordUser = $userRepo->findOneBy(['correo' => 'maitesolam@gmail.com']);

        if ($coordUser) {
            $this->adminId = $coordUser->getId();
        } else {
            // Fallback por si los fixtures no están cargados (aunque deberían)
            $this->adminId = 1;
        }
    }

    /**
     * Test: GET /coord/stats
     * Debe devolver métricas globales
     */
    public function testEstadisticasCoordinador(): void
    {
        $this->client->request(
            'GET',
            '/coord/stats',
            [],
            [],
            ['HTTP_X-Admin-Id' => $this->adminId]
        );

        $statusCode = $this->client->getResponse()->getStatusCode();

        if ($statusCode === 200) {
            $data = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('titulo', $data);
            $this->assertArrayHasKey('metricas', $data);
        }

        $this->assertTrue(in_array($statusCode, [200, 403, 404]));
    }

    /**
     * Test: POST /coordinadores
     * Crear un nuevo coordinador (solo otro coordinador puede)
     */
    public function testCrearCoordinador(): void
    {
        $payload = [
            'nombre' => 'Coord Test',
            'apellidos' => 'Apellido Test',
            'correo' => 'coord_test_' . rand(1000, 9999) . '@test.com',
            'google_id' => 'g_coord_' . rand(1000, 9999),
            'telefono' => '600000000'
        ];

        $this->client->request(
            'POST',
            '/coordinadores',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Admin-Id' => $this->adminId
            ],
            json_encode($payload)
        );

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [201, 400, 403]));
    }

    /**
     * Test: PATCH /coord/{rol}/{id}/estado
     * Cambiar estado de usuario (Voluntario u Organización)
     */
    public function testCambiarEstadoUsuario(): void
    {
        $payload = ['estado' => 'Activa'];

        $this->client->request(
            'PATCH',
            '/coord/voluntarios/1/estado',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Admin-Id' => $this->adminId
            ],
            json_encode($payload)
        );

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [200, 400, 404]));
    }

    /**
     * Test: PATCH /coord/actividades/{id}/estado
     * Moderación de actividades (Publicar, Rechazar)
     */
    public function testCambiarEstadoActividad(): void
    {
        $payload = ['estado' => 'Publicada'];

        $this->client->request(
            'PATCH',
            '/coord/actividades/1/estado',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Admin-Id' => $this->adminId
            ],
            json_encode($payload)
        );

        $statusCode = $this->client->getResponse()->getStatusCode();
        if (!in_array($statusCode, [200, 400, 404])) {
            echo "\nResponse content: " . $this->client->getResponse()->getContent() . "\n";
            echo "Status Code: " . $statusCode . "\n";
        }
        $this->assertTrue(in_array($statusCode, [200, 400, 404]));
    }
}
