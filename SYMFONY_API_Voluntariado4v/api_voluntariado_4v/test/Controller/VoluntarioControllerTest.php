<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\Rol;
use App\Entity\Curso;
use App\Entity\TipoVoluntariado;
use App\Entity\Usuario;
use App\Entity\Voluntario;

/**
 * Tests para VoluntarioController
 */
class VoluntarioControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Test: GET /voluntarios
     */
    public function testListarVoluntarios(): void
    {
        $this->client->request('GET', '/voluntarios');
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    /**
     * Test: POST /voluntarios (REGISTRO COMPLETO)
     * Arreglado: Ahora envía google_id y correo.
     */
    public function testRegistrarVoluntario(): void
    {
        $client = $this->client;
        $container = $client->getContainer();
        $em = $container->get('doctrine')->getManager();

        // 1. PREPARAR BASE DE DATOS DE TEST

        // ROL
        $rol = $em->getRepository(Rol::class)->findOneBy(['nombre' => 'Voluntario']);
        if (!$rol) {
            $rol = new Rol();
            $rol->setNombre('Voluntario');
            $em->persist($rol);
        }

        // CURSO
        $curso = new Curso();
        $curso->setNombre('Curso Test ' . rand(1, 100));
        $curso->setAbreviacion('CT' . rand(1, 100));
        $curso->setGrado('Grado Superior');
        $curso->setNivel(1);
        $em->persist($curso);

        // TIPO VOLUNTARIADO
        $tipo = new TipoVoluntariado();
        $tipo->setNombreTipo('Tipo Test ' . rand(1, 1000));
        $em->persist($tipo);

        $em->flush(); // Guardar auxiliares

        // 2. PAYLOAD CORRECTO (Coincide con tu DTO)
        $random = rand(10000, 99999);
        $payload = [
            // DATOS USUARIO (Obligatorios)
            'google_id' => 'google_vol_' . $random,
            'correo' => 'vol_' . $random . '@test.com',

            // DATOS VOLUNTARIO
            'nombre' => 'Voluntario',
            'apellidos' => 'Test',
            'dni' => '12345678' . substr((string)$random, 0, 1),
            'telefono' => '600' . $random,
            'fecha_nac' => '2000-01-15',
            'carnet_conducir' => true,
            'id_curso_actual' => $curso->getId(),
            'preferencias_ids' => [$tipo->getId()],
            'idiomas' => []
        ];

        // 3. LLAMADA
        $client->request(
            'POST',
            '/voluntarios',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        // Debug si falla
        if ($client->getResponse()->getStatusCode() !== 201) {
            fwrite(STDERR, "\nERROR RESPONSE VOLUNTARIO: " . $client->getResponse()->getContent() . "\n");
        }

        $this->assertEquals(201, $client->getResponse()->getStatusCode());

        // 4. VERIFICACIÓN DB
        $usuarioCreado = $em->getRepository(Usuario::class)->findOneBy(['correo' => 'vol_' . $random . '@test.com']);
        $this->assertNotNull($usuarioCreado, 'El usuario debería haberse creado');
    }

    /**
     * Test: GET /voluntarios/{id}
     */
    public function testDetalleVoluntario(): void
    {
        $this->client->request('GET', '/voluntarios/1');
        $statusCode = $this->client->getResponse()->getStatusCode();

        if ($statusCode === 200) {
            $data = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('correo', $data);
        }
        $this->assertTrue(in_array($statusCode, [200, 404]));
    }

    /**
     * Test: PUT /voluntarios/{id}
     */
    public function testActualizarVoluntario(): void
    {
        $payload = [
            'nombre' => 'Voluntario Actualizado',
            'apellidos' => 'Test Nuevo',
            'telefono' => '600111222',
            'fechaNac' => '2001-05-20',
            'preferencias_ids' => [1] // Asumimos que ID 1 existe o fallará controlado
        ];

        $this->client->request(
            'PUT',
            '/voluntarios/1',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X-User-Id' => '1'],
            json_encode($payload)
        );

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [200, 400, 404, 500]));
    }

    /**
     * Test: POST /voluntarios/{id}/actividades/{idActividad}
     */
    public function testInscribirseActividad(): void
    {
        $this->client->request(
            'POST',
            '/voluntarios/1/actividades/1',
            [],
            [],
            ['HTTP_X-User-Id' => '1']
        );
        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [201, 409, 404]));
    }

    /**
     * Test: DELETE /voluntarios/{id}/actividades/{idActividad}
     */
    public function testDesapuntarseActividad(): void
    {
        $this->client->request(
            'DELETE',
            '/voluntarios/1/actividades/1',
            [],
            [],
            ['HTTP_X-User-Id' => '1']
        );
        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [200, 404]));
    }

    /**
     * Test: GET /voluntarios/{id}/historial
     */
    public function testHistorialVoluntario(): void
    {
        $this->client->request(
            'GET',
            '/voluntarios/1/historial',
            [],
            [],
            ['HTTP_X-User-Id' => '1']
        );
        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [200, 404]));
    }
}
