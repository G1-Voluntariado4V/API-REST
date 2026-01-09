<?php

namespace App\Tests\Entity;

use App\Entity\Organizacion;
use App\Entity\Usuario;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para la entidad Organizacion
 */
class OrganizacionEntityTest extends TestCase
{
    /**
     * Test: Crear instancia de Organizacion
     */
    public function testCrearOrganizacion(): void
    {
        $organizacion = new Organizacion();

        $this->assertInstanceOf(Organizacion::class, $organizacion);
        $this->assertNull($organizacion->getId());
    }

    /**
     * Test: Setters y Getters básicos
     */
    public function testSettersYGetters(): void
    {
        $organizacion = new Organizacion();

        $organizacion->setNombre('ONG Test');
        $organizacion->setDescripcion('Descripción de prueba');
        $organizacion->setCif('B12345678');
        $organizacion->setTelefono('600123456');
        $organizacion->setDireccion('Calle Test 123');
        $organizacion->setSitioWeb('https://test.org');

        $this->assertEquals('ONG Test', $organizacion->getNombre());
        $this->assertEquals('Descripción de prueba', $organizacion->getDescripcion());
        $this->assertEquals('B12345678', $organizacion->getCif());
        $this->assertEquals('600123456', $organizacion->getTelefono());
        $this->assertEquals('Calle Test 123', $organizacion->getDireccion());
        $this->assertEquals('https://test.org', $organizacion->getSitioWeb());
    }

    /**
     * Test: Relación con Usuario
     */
    public function testRelacionConUsuario(): void
    {
        $organizacion = new Organizacion();
        $usuario = $this->createMock(Usuario::class);

        $usuario->method('getId')->willReturn(1);

        $organizacion->setUsuario($usuario);

        $this->assertSame($usuario, $organizacion->getUsuario());
        $this->assertEquals(1, $organizacion->getId());
    }

    /**
     * Test: Valores null permitidos
     */
    public function testValoresNullPermitidos(): void
    {
        $organizacion = new Organizacion();

        $this->assertNull($organizacion->getCif());
        $this->assertNull($organizacion->getNombre());
        $this->assertNull($organizacion->getDescripcion());
        $this->assertNull($organizacion->getDireccion());
        $this->assertNull($organizacion->getSitioWeb());
        $this->assertNull($organizacion->getTelefono());
    }
}
