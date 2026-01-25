<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests funcionales para CatalogoController
 * 
 * RUTAS REALES:
 * - GET /catalogos/cursos
 * - GET /catalogos/idiomas
 * - GET /catalogos/tipos-voluntariado
 */
class CatalogoControllerTest extends WebTestCase
{
    // ========================================================================
    // TESTS DE CURSOS
    // ========================================================================

    public function testListarCursosDevuelve200(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogos/cursos');

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testListarCursosDevuelveJSON(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogos/cursos');

        $this->assertJson($client->getResponse()->getContent());
    }

    public function testListarCursosDevuelveArray(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogos/cursos');

        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
    }

    // ========================================================================
    // TESTS DE IDIOMAS
    // ========================================================================

    public function testListarIdiomasDevuelve200(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogos/idiomas');

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testListarIdiomasDevuelveJSON(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogos/idiomas');

        $this->assertJson($client->getResponse()->getContent());
    }

    public function testListarIdiomasDevuelveArray(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogos/idiomas');

        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
    }

    // ========================================================================
    // TESTS DE TIPOS DE VOLUNTARIADO
    // ========================================================================

    public function testListarTiposDevuelve200(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogos/tipos-voluntariado');

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testListarTiposDevuelveJSON(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogos/tipos-voluntariado');

        $this->assertJson($client->getResponse()->getContent());
    }

    public function testListarTiposDevuelveArray(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogos/tipos-voluntariado');

        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
    }

    // ========================================================================
    // TESTS DE MÉTODOS HTTP
    // ========================================================================

    public function testCatalogosSoloAceptaGET(): void
    {
        $client = static::createClient();

        // POST no permitido (Cursos)
        $client->request('POST', '/catalogos/cursos');
        $this->assertEquals(Response::HTTP_METHOD_NOT_ALLOWED, $client->getResponse()->getStatusCode());

        // PUT no permitido (Idiomas)
        $client->request('PUT', '/catalogos/idiomas');
        $this->assertEquals(Response::HTTP_METHOD_NOT_ALLOWED, $client->getResponse()->getStatusCode());
    }

    // ========================================================================
    // TESTS DE CONTENIDO
    // ========================================================================

    public function testCursosContieneEstructuraCorrecta(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogos/cursos');

        $content = json_decode($client->getResponse()->getContent(), true);

        // Verificar que es un array
        $this->assertIsArray($content);

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

        $client->request('GET', '/catalogos/idiomas');

        $content = json_decode($client->getResponse()->getContent(), true);

        // Verificar que es un array
        $this->assertIsArray($content);

        if (count($content) > 0) {
            $idioma = $content[0];
            $this->assertArrayHasKey('id', $idioma);
            $this->assertArrayHasKey('nombre', $idioma);
        }

        $this->assertTrue(true);
    }

    public function testTiposContieneEstructuraCorrecta(): void
    {
        $client = static::createClient();

        $client->request('GET', '/catalogos/tipos-voluntariado');

        $content = json_decode($client->getResponse()->getContent(), true);

        // Verificar que es un array
        $this->assertIsArray($content);

        if (count($content) > 0) {
            $tipo = $content[0];
            $this->assertArrayHasKey('id', $tipo);
            $this->assertArrayHasKey('nombreTipo', $tipo);
        }

        $this->assertTrue(true);
    }
    // ========================================================================
    // TESTS CRUD TIPOS VOLUNTARIADO
    // ========================================================================

    public function testCrearTipoVoluntariado(): void
    {
        $client = static::createClient();
        $client->request('POST', '/catalogos/tipos-voluntariado', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'nombreTipo' => 'Tipo Test'
        ]));

        $this->assertEquals(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $content);
        $this->assertEquals('Tipo Test', $content['nombreTipo']);
    }

    public function testActualizarTipoVoluntariado(): void
    {
        $client = static::createClient();
        // Primero creamos uno
        $client->request('POST', '/catalogos/tipos-voluntariado', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'nombreTipo' => 'Para Actualizar'
        ]));
        $data = json_decode($client->getResponse()->getContent(), true);
        $id = $data['id'];

        // Ahora actualizamos
        $client->request('PUT', '/catalogos/tipos-voluntariado/' . $id, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'nombreTipo' => 'Actualizado'
        ]));

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Actualizado', $content['nombreTipo']);
    }

    public function testEliminarTipoVoluntariado(): void
    {
        $client = static::createClient();
        // Primero creamos uno
        $client->request('POST', '/catalogos/tipos-voluntariado', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'nombreTipo' => 'Para Borrar'
        ]));
        $data = json_decode($client->getResponse()->getContent(), true);
        $id = $data['id'];

        // Ahora borramos
        $client->request('DELETE', '/catalogos/tipos-voluntariado/' . $id);
        $this->assertEquals(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        // Verificamos que ya no existe intentando borrarlo de nuevo (404)
        $client->request('DELETE', '/catalogos/tipos-voluntariado/' . $id);
        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }
}
