<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests funcionales para CoordinadorController
 */
class CoordinadorControllerTest extends WebTestCase
{
    // ========================================================================
    // TESTS DE DASHBOARD
    // ========================================================================

    public function testDashboardSinAutenticacionDevuelve403(): void
    {
        $client = static::createClient();

        $client->request('GET', '/coordinadores/dashboard');

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [
            Response::HTTP_FORBIDDEN,
            Response::HTTP_UNAUTHORIZED
        ]);
    }

    public function testDashboardDevuelveJSON(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/coordinadores/dashboard',
            [],
            [],
            ['HTTP_X-Admin-Id' => '1']
        );

        $this->assertJson($client->getResponse()->getContent());
    }

    // ========================================================================
    // TESTS DE PERFIL (GET /coordinadores/{id})
    // ========================================================================

    public function testPerfilCoordinadorInexistente(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/coordinadores/999999',
            [],
            [],
            ['HTTP_X-Admin-Id' => '1']
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    // ========================================================================
    // TESTS DE REGISTRO (POST /coordinadores)
    // ========================================================================

    public function testRegistrarCoordinadorSinDatos(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/coordinadores',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Admin-Id' => '1'
            ],
            json_encode([])
        );

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [
            Response::HTTP_UNPROCESSABLE_ENTITY,
            Response::HTTP_BAD_REQUEST
        ]);
    }

    // ========================================================================
    // TESTS DE ACTUALIZACIÓN (PUT /coordinadores/{id})
    // ========================================================================

    public function testActualizarCoordinadorInexistente(): void
    {
        $client = static::createClient();

        $client->request(
            'PUT',
            '/coordinadores/999999',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Admin-Id' => '1'
            ],
            json_encode([
                'nombre' => 'Coordinador Actualizado',
                'apellidos' => 'Apellidos'
            ])
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    // ========================================================================
    // TESTS DE MODERACIÓN DE USUARIOS
    // ========================================================================

    public function testCambiarEstadoUsuarioSinAuthentication(): void
    {
        $client = static::createClient();

        $client->request(
            'PATCH',
            '/coordinadores/usuarios/1/voluntario/estado',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['estado' => 'Activa'])
        );

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [
            Response::HTTP_FORBIDDEN,
            Response::HTTP_UNAUTHORIZED
        ]);
    }

    public function testCambiarEstadoUsuarioInexistente(): void
    {
        $client = static::createClient();

        $client->request(
            'PATCH',
            '/coordinadores/usuarios/999999/voluntario/estado',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Admin-Id' => '1'
            ],
            json_encode(['estado' => 'Activa'])
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    // ========================================================================
    // TESTS DE GESTIÓN DE ACTIVIDADES
    // ========================================================================

    public function testListarActividadesGlobalSinAuth(): void
    {
        $client = static::createClient();

        $client->request('GET', '/coordinadores/actividades');

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [
            Response::HTTP_FORBIDDEN,
            Response::HTTP_UNAUTHORIZED
        ]);
    }

    public function testCambiarEstadoActividadInexistente(): void
    {
        $client = static::createClient();

        $client->request(
            'PATCH',
            '/coordinadores/actividades/999999/estado',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Admin-Id' => '1'
            ],
            json_encode(['estado' => 'Publicada'])
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testBorrarActividadInexistente(): void
    {
        $client = static::createClient();

        $client->request(
            'DELETE',
            '/coordinadores/actividades/999999',
            [],
            [],
            ['HTTP_X-Admin-Id' => '1']
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testEditarActividadInexistente(): void
    {
        $client = static::createClient();

        $client->request(
            'PUT',
            '/coordinadores/actividades/999999',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Admin-Id' => '1'
            ],
            json_encode([
                'titulo' => 'Título editado'
            ])
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    // ========================================================================
    // TESTS DE ELIMINACIÓN DE USUARIOS
    // ========================================================================

    public function testEliminarUsuarioInexistente(): void
    {
        $client = static::createClient();

        $client->request(
            'DELETE',
            '/coordinadores/usuarios/999999',
            [],
            [],
            ['HTTP_X-Admin-Id' => '1']
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    // ========================================================================
    // TESTS DE SEGURIDAD
    // ========================================================================

    public function testAccesoConAdminIdInvalido(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/coordinadores/dashboard',
            [],
            [],
            ['HTTP_X-Admin-Id' => '999999999']
        );

        $statusCode = $client->getResponse()->getStatusCode();
        // Debería devolver error de autenticación o forbidden
        $this->assertContains($statusCode, [
            Response::HTTP_FORBIDDEN,
            Response::HTTP_UNAUTHORIZED,
            Response::HTTP_NOT_FOUND
        ]);
    }
}
