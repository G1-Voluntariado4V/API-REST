<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests para endpoints de catálogos basados en OpenAPI YAML
 */
class CatalogosControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Test: GET /catalogos/ods
     * Debe devolver lista de ODS
     */
    public function testListarODS(): void
    {
        $this->client->request('GET', '/catalogos/ods');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    /**
     * Test: GET /catalogos/idiomas
     * Debe devolver lista de idiomas
     */
    public function testListarIdiomas(): void
    {
        $this->client->request('GET', '/catalogos/idiomas');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    /**
     * Test: GET /catalogos/tipos-voluntariado
     * Debe devolver lista de tipos de voluntariado
     */
    public function testListarTiposVoluntariado(): void
    {
        $this->client->request('GET', '/catalogos/tipos-voluntariado');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    /**
     * Test: GET /catalogos/cursos
     * Debe devolver lista de cursos
     */
    public function testListarCursos(): void
    {
        $this->client->request('GET', '/catalogos/cursos');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }
}
