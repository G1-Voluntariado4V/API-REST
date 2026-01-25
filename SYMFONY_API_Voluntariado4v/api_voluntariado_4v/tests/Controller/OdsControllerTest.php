<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class OdsControllerTest extends WebTestCase
{
    public function testListarOds(): void
    {
        $client = static::createClient();
        $client->request('GET', '/ods');
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $this->assertJson($client->getResponse()->getContent());
    }

    public function testCrearOds(): void
    {
        $client = static::createClient();
        $client->request('POST', '/ods', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'nombre' => 'ODS Test',
            'descripcion' => 'Descripción Test'
        ]));

        $this->assertEquals(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $content);
        $this->assertEquals('ODS Test', $content['nombre']);
    }

    public function testActualizarOds(): void
    {
        $client = static::createClient();
        // Crear
        $client->request('POST', '/ods', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'nombre' => 'ODS Para Actualizar',
            'descripcion' => 'Desc'
        ]));
        $data = json_decode($client->getResponse()->getContent(), true);
        $id = $data['id'];

        // Actualizar
        $client->request('PUT', '/ods/' . $id, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'nombre' => 'ODS Actualizado'
        ]));

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('ODS Actualizado', $content['nombre']);
    }

    public function testEliminarOds(): void
    {
        $client = static::createClient();
        // Crear
        $client->request('POST', '/ods', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'nombre' => 'ODS Para Borrar',
            'descripcion' => 'Desc'
        ]));
        $data = json_decode($client->getResponse()->getContent(), true);
        $id = $data['id'];

        // Borrar
        $client->request('DELETE', '/ods/' . $id);
        $this->assertEquals(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        // Verificar 404
        $client->request('DELETE', '/ods/' . $id);
        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testSubirYBorrarImagenOds(): void
    {
        $client = static::createClient();

        // 1. Crear ODS
        $client->request('POST', '/ods', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'nombre' => 'ODS con Imagen',
            'descripcion' => 'Test Imagen'
        ]));
        $data = json_decode($client->getResponse()->getContent(), true);
        $id = $data['id'];

        // 2. Crear imagen temporal
        $tempFile = tempnam(sys_get_temp_dir(), 'test_img');
        file_put_contents($tempFile, 'fake image content');

        // Simular UploadedFile (path, originalName, mimeType, error, test)
        // Nota: para que pase validación de extensión, usaremos una extensión correcta en el nombre original.
        // Pero el contenido es falso, esperemos que el controlador no valide el mime-type real con getMimeType() si solo chequea extensión.
        // El controlador usa: $extension = strtolower($file->getClientOriginalExtension());
        // Así que 'test.jpg' debería funcionar.

        $uploadedFile = new UploadedFile(
            $tempFile,
            'test.jpg',
            'image/jpeg',
            null,
            true // test mode
        );

        // 3. Subir Imagen
        $client->request('POST', '/ods/' . $id . '/imagen', [], [
            'imagen' => $uploadedFile
        ]);

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('img_ods', $response);
        $this->assertArrayHasKey('img_url', $response);

        $filename = $response['img_ods'];

        // 4. Verificar que se puede ver en el GET del ODS (opcional, pero buena práctica)
        // Ojo: el GET devuelve una lista, tendríamos que buscarlo. O simplemente confiar en la respuesta del POST.

        // 5. Borrar Imagen
        $client->request('DELETE', '/ods/' . $id . '/imagen');
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 6. Verificar que si pedimos borrar otra vez, dice que no tiene imagen (o 200 con mensaje)
        // El controlador retorna 200 y 'El ODS no tiene imagen asignada' si no tiene.
        $client->request('DELETE', '/ods/' . $id . '/imagen');
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $msg = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('El ODS no tiene imagen asignada', $msg['mensaje']);
    }
}
