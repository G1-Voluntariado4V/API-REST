<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CatalogosControllerTest extends WebTestCase
{
    // Testea que el catálogo de ODS es público y devuelve datos
    public function testListarODS(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/catalogos/ods');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertIsArray($data);
    }

    // Testea catálogo de idiomas
    public function testListarIdiomas(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/catalogos/idiomas');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
    }

    // Testea catálogo de tipos de voluntariado
    public function testListarTipos(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/catalogos/tipos-voluntariado');

        $this->assertResponseIsSuccessful();
    }
}
