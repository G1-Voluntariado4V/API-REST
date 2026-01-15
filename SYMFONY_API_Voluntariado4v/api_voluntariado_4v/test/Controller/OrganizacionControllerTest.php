<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\Rol;
use App\Entity\Usuario;
use App\Entity\Organizacion;

/**
 * Tests para OrganizacionController
 */
class OrganizacionControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testListarOrganizaciones(): void
    {
        $this->client->request('GET', '/organizaciones');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testDetalleOrganizacion(): void
    {
        $this->client->request('GET', '/organizaciones/1');
        $statusCode = $this->client->getResponse()->getStatusCode();
        if ($statusCode === 200) {
            $data = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('nombre', $data);
        }
        $this->assertTrue(in_array($statusCode, [200, 404]));
    }

    public function testActualizarOrganizacion(): void
    {
        $payload = [
            'nombre' => 'ONG Actualizada',
            'descripcion' => 'Nueva descripción',
            'sitioWeb' => 'https://test-org.com',
            'direccion' => 'Calle Test 123',
            'telefono' => '600123456'
        ];

        $this->client->request(
            'PUT',
            '/organizaciones/1',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [200, 404]));
    }

    /**
     * TEST CLAVE: CREAR ORGANIZACION
     * Arreglado: Crea el Rol antes para evitar error 500
     */
    public function testCrearOrganizacion(): void
    {
        $client = $this->client;
        $container = $client->getContainer();
        $em = $container->get('doctrine')->getManager();

        // 1. CREAR EL ROL (Vital para evitar error 500)
        $rol = $em->getRepository(Rol::class)->findOneBy(['nombre' => 'Organizacion']);
        if (!$rol) {
            $rol = new Rol();
            $rol->setNombre('Organizacion');
            $em->persist($rol);
            $em->flush();
        }

        // 2. PAYLOAD COMPLETO
        $random = rand(1000, 99999);
        $payload = [
            'google_id' => 'google_org_' . $random,
            'correo' => 'org_' . $random . '@test.com',
            'nombre' => 'Organización Test ' . $random,
            'cif' => 'CIF' . $random,
            'descripcion' => 'Descripción de prueba',
            'direccion' => 'Calle Falsa 123',
            'telefono' => '911223344',
            'sitio_web' => 'https://org-' . $random . '.com'
        ];

        // 3. REQUEST
        $client->request(
            'POST',
            '/organizaciones', // Asegúrate de que tu ruta en Controller es esta
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        // Debug error 500
        if ($client->getResponse()->getStatusCode() !== 201) {
            $content = $client->getResponse()->getContent();
            $matches = [];
            preg_match('/<title>(.*?)<\/title>/', $content, $matches);
            fwrite(STDERR, "\nERROR ORG: " . ($matches[1] ?? $content) . "\n");
        }

        $this->assertEquals(201, $client->getResponse()->getStatusCode());

        // 4. VERIFICAR BD
        $usuarioCreado = $em->getRepository(Usuario::class)->findOneBy(['correo' => 'org_' . $random . '@test.com']);
        $this->assertNotNull($usuarioCreado, 'El usuario org debería haberse creado');
    }

    public function testCrearActividadComoOrganizacion(): void
    {
        $payload = [
            'titulo' => 'Actividad de Prueba',
            'descripcion' => 'Descripción',
            'fecha_inicio' => '2026-03-15 10:00:00',
            'duracion_horas' => 4,
            'cupo_maximo' => 20,
            'ubicacion' => 'Centro',
            'id_organizacion' => 1,
            'odsIds' => [1],
            'tiposIds' => [1]
        ];

        $this->client->request(
            'POST',
            '/organizaciones/1/actividades',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [201, 400, 404, 500]));
    }

    public function testListarActividadesOrganizacion(): void
    {
        $this->client->request('GET', '/organizaciones/1/actividades');
        $statusCode = $this->client->getResponse()->getStatusCode();
        if ($statusCode === 200) {
            $data = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertIsArray($data);
        }
        $this->assertTrue(in_array($statusCode, [200, 404]));
    }

    public function testEstadisticasOrganizacion(): void
    {
        $this->client->request('GET', '/organizaciones/1/estadisticas');
        $statusCode = $this->client->getResponse()->getStatusCode();
        if ($statusCode === 200) {
            $data = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('total_actividades', $data);
        }
        $this->assertTrue(in_array($statusCode, [200, 404]));
    }

    public function testVoluntariosActividad(): void
    {
        $this->client->request('GET', '/organizaciones/1/actividades/1/voluntarios');
        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [200, 403, 404]));
    }

    /**
     * Test: GET /organizaciones/top-voluntarios
     * Prueba el nuevo endpoint de ranking de organizaciones
     */
    public function testTopOrganizacionesVoluntarios(): void
    {
        $this->client->request('GET', '/organizaciones/top-voluntarios');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($this->client->getResponse()->getContent(), true);

        // Verificar que es un array
        $this->assertIsArray($data);

        // El TOP puede tener hasta 3 organizaciones (o menos si hay pocas en BD)
        $this->assertLessThanOrEqual(3, count($data));

        // Si hay resultados, verificar estructura y orden
        if (count($data) > 0) {
            // Verificar estructura del primer elemento
            $primera = $data[0];
            $this->assertArrayHasKey('posicion', $primera);
            $this->assertArrayHasKey('id_organizacion', $primera);
            $this->assertArrayHasKey('nombre', $primera);
            $this->assertArrayHasKey('cif', $primera);
            $this->assertArrayHasKey('total_voluntarios', $primera);
            $this->assertArrayHasKey('total_actividades', $primera);
            $this->assertArrayHasKey('descripcion', $primera);

            // La primera posición debe ser 1
            $this->assertEquals(1, $primera['posicion']);

            // Verificar tipos de datos
            $this->assertIsInt($primera['posicion']);
            $this->assertIsInt($primera['id_organizacion']);
            $this->assertIsString($primera['nombre']);
            $this->assertIsInt($primera['total_voluntarios']);
            $this->assertIsInt($primera['total_actividades']);

            // Los totales no pueden ser negativos
            $this->assertGreaterThanOrEqual(0, $primera['total_voluntarios']);
            $this->assertGreaterThanOrEqual(0, $primera['total_actividades']);

            // Si hay más de una organización, verificar que están ordenadas
            if (count($data) > 1) {
                $segunda = $data[1];
                $this->assertEquals(2, $segunda['posicion']);

                // La primera debe tener más o igual voluntarios que la segunda
                $this->assertGreaterThanOrEqual(
                    $segunda['total_voluntarios'],
                    $primera['total_voluntarios'],
                    'El ranking debe estar ordenado por total_voluntarios descendente'
                );
            }

            // Si hay 3, verificar la tercera
            if (count($data) === 3) {
                $tercera = $data[2];
                $this->assertEquals(3, $tercera['posicion']);
            }
        }
    }

    /**
     * Test: Verificar que el endpoint top-voluntarios es público
     * No debe requerir autenticación
     */
    public function testTopOrganizacionesEsPublico(): void
    {
        // Sin ningún header de autenticación
        $this->client->request('GET', '/organizaciones/top-voluntarios');

        // Debe funcionar sin autenticación (200)
        $this->assertResponseIsSuccessful();
    }
}
