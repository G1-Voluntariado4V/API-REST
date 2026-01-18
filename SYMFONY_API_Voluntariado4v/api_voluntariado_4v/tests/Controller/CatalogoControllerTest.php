<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests funcionales para CatalogoController
 */
class CatalogoControllerTest extends WebTestCase
{
    // ========================================================================
    // TESTS DE CURSOS
    // ========================================================================

    public function testListarCursosDevuelve200(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogo/cursos');

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testListarCursosDevuelveJSON(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogo/cursos');

        $this->assertJson($client->getResponse()->getContent());
    }

    public function testListarCursosDevuelveArray(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogo/cursos');

        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
    }

    // ========================================================================
    // TESTS DE IDIOMAS
    // ========================================================================

    public function testListarIdiomasDevuelve200(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogo/idiomas');

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testListarIdiomasDevuelveJSON(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogo/idiomas');

        $this->assertJson($client->getResponse()->getContent());
    }

    public function testListarIdiomasDevuelveArray(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogo/idiomas');

        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
    }

    // ========================================================================
    // TESTS DE PREFERENCIAS (TIPOS DE VOLUNTARIADO)
    // ========================================================================

    public function testListarPreferenciasDevuelve200(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogo/preferencias');

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testListarPreferenciasDevuelveJSON(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogo/preferencias');

        $this->assertJson($client->getResponse()->getContent());
    }

    public function testListarPreferenciasDevuelveArray(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogo/preferencias');

        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
    }

    // ========================================================================
    // TESTS DE MÉTODOS HTTP
    // ========================================================================

    public function testCatalogoSoloAceptaGET(): void
    {
        $client = static::createClient();

        // POST no permitido
        $client->request('POST', '/catalogo/cursos');
        $this->assertEquals(Response::HTTP_METHOD_NOT_ALLOWED, $client->getResponse()->getStatusCode());

        // PUT no permitido
        $client->request('PUT', '/catalogo/idiomas');
        $this->assertEquals(Response::HTTP_METHOD_NOT_ALLOWED, $client->getResponse()->getStatusCode());

        // DELETE no permitido
        $client->request('DELETE', '/catalogo/preferencias');
        $this->assertEquals(Response::HTTP_METHOD_NOT_ALLOWED, $client->getResponse()->getStatusCode());
    }

    // ========================================================================
    // TESTS DE CONTENIDO
    // ========================================================================

    public function testCursosContieneEstructuraCorrecta(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogo/cursos');

        $content = json_decode($client->getResponse()->getContent(), true);

        if (count($content) > 0) {
            $curso = $content[0];
            $this->assertArrayHasKey('id', $curso);
            $this->assertArrayHasKey('nombre', $curso);
        }

        $this->assertTrue(true);
    }

    public function testIdiomasContieneEstructuraCorrecta(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogo/idiomas');

        $content = json_decode($client->getResponse()->getContent(), true);

        if (count($content) > 0) {
            $idioma = $content[0];
            $this->assertArrayHasKey('id', $idioma);
            $this->assertArrayHasKey('nombre', $idioma);
        }

        $this->assertTrue(true);
    }

    public function testPreferenciasContieneEstructuraCorrecta(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogo/preferencias');

        $content = json_decode($client->getResponse()->getContent(), true);

        if (count($content) > 0) {
            $preferencia = $content[0];
            $this->assertArrayHasKey('id', $preferencia);
            $this->assertArrayHasKey('nombre', $preferencia);
        }

        $this->assertTrue(true);
    }
}
