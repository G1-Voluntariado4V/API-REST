<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests funcionales para InscripcionController
 */
class InscripcionControllerTest extends WebTestCase
{
    // ========================================================================
    // TESTS DE LISTADO DE INSCRIPCIONES
    // ========================================================================

    public function testListarInscripcionesActividadInexistente(): void
    {
        $client = static::createClient();

        $client->request('GET', '/actividades/999999/inscripciones');

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testListarInscripcionesDevuelveJSON(): void
    {
        $client = static::createClient();

        $client->request('GET', '/actividades/1/inscripciones');

        $this->assertJson($client->getResponse()->getContent());
    }

    // ========================================================================
    // TESTS DE CAMBIO DE ESTADO (PATCH)
    // ========================================================================

    public function testCambiarEstadoInscripcionInexistente(): void
    {
        $client = static::createClient();

        $client->request(
            'PATCH',
            '/actividades/999999/inscripciones/999999',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['estado' => 'Aceptado'])
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testCambiarEstadoSinDatos(): void
    {
        $client = static::createClient();

        $client->request(
            'PATCH',
            '/actividades/1/inscripciones/1',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [
            Response::HTTP_UNPROCESSABLE_ENTITY,
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_NOT_FOUND
        ]);
    }

    public function testCambiarEstadoConValorInvalido(): void
    {
        $client = static::createClient();

        $client->request(
            'PATCH',
            '/actividades/1/inscripciones/1',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['estado' => 'EstadoInvalido'])
        );

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [
            Response::HTTP_UNPROCESSABLE_ENTITY,
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_NOT_FOUND
        ]);
    }

    // ========================================================================
    // TESTS DE MÉTODOS HTTP
    // ========================================================================

    public function testInscripcionesNoAceptaPOST(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/actividades/1/inscripciones',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertEquals(Response::HTTP_METHOD_NOT_ALLOWED, $client->getResponse()->getStatusCode());
    }

    public function testInscripcionesNoAceptaDELETE(): void
    {
        $client = static::createClient();

        $client->request('DELETE', '/actividades/1/inscripciones/1');

        $this->assertEquals(Response::HTTP_METHOD_NOT_ALLOWED, $client->getResponse()->getStatusCode());
    }
}
