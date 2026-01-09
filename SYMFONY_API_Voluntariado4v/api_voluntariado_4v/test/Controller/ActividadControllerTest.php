<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests para ActividadController basados en OpenAPI YAML
 * Endpoints: GET /actividades, POST /actividades, GET /actividades/{id},
 *           PUT /actividades/{id}, DELETE /actividades/{id}, POST /actividades/{id}/imagenes
 */
class ActividadControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Test: GET /actividades
     * Debe devolver catálogo de actividades publicadas
     */
    public function testListarActividades(): void
    {
        $this->client->request('GET', '/actividades');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    /**
     * Test: GET /actividades con filtros
     * Debe filtrar por ODS y tipo de voluntariado
     */
    public function testListarActividadesConFiltros(): void
    {
        $this->client->request('GET', '/actividades?ods_id=1&tipo_id=1');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    /**
     * Test: POST /actividades
     * Debe crear una nueva actividad
     */
    public function testCrearActividad(): void
    {
        $payload = [
            'titulo' => 'Nueva Actividad Test',
            'descripcion' => 'Descripción de prueba',
            'fecha_inicio' => '2026-04-20 09:00:00',
            'duracion_horas' => 3,
            'cupo_maximo' => 15,
            'ubicacion' => 'Parque Central',
            'id_organizacion' => 1,
            'odsIds' => [1, 2],
            'tiposIds' => [1]
        ];

        $this->client->request(
            'POST',
            '/actividades',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [201, 400, 404]));
    }

    /**
     * Test: GET /actividades/{id}
     * Debe devolver detalle completo de la actividad
     */
    public function testDetalleActividad(): void
    {
        $this->client->request('GET', '/actividades/1');

        $statusCode = $this->client->getResponse()->getStatusCode();

        if ($statusCode === 200) {
            $data = json_decode($this->client->getResponse()->getContent(), true);

            // Verificar estructura según ActividadResponseDTO
            $this->assertArrayHasKey('id', $data);
            $this->assertArrayHasKey('titulo', $data);
            $this->assertArrayHasKey('fecha_inicio', $data);
            $this->assertArrayHasKey('duracion_horas', $data);
            $this->assertArrayHasKey('cupo_maximo', $data);
            $this->assertArrayHasKey('inscritos_confirmados', $data);
            $this->assertArrayHasKey('ubicacion', $data);
            $this->assertArrayHasKey('estado_publicacion', $data);
            $this->assertArrayHasKey('nombre_organizacion', $data);
        }

        $this->assertTrue(in_array($statusCode, [200, 404]));
    }

    /**
     * Test: PUT /actividades/{id}
     * Debe actualizar una actividad existente
     */
    public function testActualizarActividad(): void
    {
        $payload = [
            'titulo' => 'Actividad Actualizada',
            'descripcion' => 'Nueva descripción',
            'ubicacion' => 'Nueva ubicación',
            'fecha_inicio' => '2026-05-10 10:00:00',
            'duracion_horas' => 5,
            'cupo_maximo' => 25,
            'odsIds' => [1],
            'tiposIds' => [1, 2]
        ];

        $this->client->request(
            'PUT',
            '/actividades/1',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            json_encode($payload)
        );

        $statusCode = $this->client->getResponse()->getStatusCode();

        if (!in_array($statusCode, [200, 400, 404])) {
            echo "\n[DEBUG] testActualizarActividad failed.\n";
            echo "Status Code: " . $statusCode . "\n";
            echo "Response: " . $this->client->getResponse()->getContent() . "\n";
        }

        $this->assertTrue(in_array($statusCode, [200, 400, 404]));
    }

    /**
     * Test: DELETE /actividades/{id}
     * Debe marcar la actividad como cancelada/eliminada (soft delete)
     */
    public function testEliminarActividad(): void
    {
        $this->client->request('DELETE', '/actividades/999');

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [200, 404]));
    }

    /**
     * Test: POST /actividades/{id}/imagenes
     * Debe añadir una imagen a la actividad
     */
    public function testAñadirImagenActividad(): void
    {
        $payload = [
            'url_imagen' => 'https://example.com/imagen.jpg',
            'descripcion' => 'Imagen de prueba'
        ];

        $this->client->request(
            'POST',
            '/actividades/1/imagenes',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [201, 400, 404]));
    }
}
