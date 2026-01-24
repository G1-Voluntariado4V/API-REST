<?php

namespace App\Tests\Entity;

use App\Entity\Organizacion;
use App\Entity\Usuario;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para la entidad Organizacion
 */
class OrganizacionTest extends TestCase
{
    private Organizacion $organizacion;

    protected function setUp(): void
    {
        $this->organizacion = new Organizacion();
    }

    // ========================================================================
    // TESTS DE PROPIEDADES BÁSICAS
    // ========================================================================

    public function testOrganizacionInstanciacionCorrecta(): void
    {
        $this->assertInstanceOf(Organizacion::class, $this->organizacion);
        $this->assertNull($this->organizacion->getId());
    }

    public function testSetYGetCif(): void
    {
        $cif = 'G12345678';
        $this->organizacion->setCif($cif);
        $this->assertEquals($cif, $this->organizacion->getCif());
    }

    public function testCifPuedeSerNulo(): void
    {
        $this->organizacion->setCif(null);
        $this->assertNull($this->organizacion->getCif());
    }

    public function testSetYGetNombre(): void
    {
        $nombre = 'ONG Ejemplo';
        $this->organizacion->setNombre($nombre);
        $this->assertEquals($nombre, $this->organizacion->getNombre());
    }

    public function testNombrePuedeSerNulo(): void
    {
        $this->organizacion->setNombre(null);
        $this->assertNull($this->organizacion->getNombre());
    }

    public function testSetYGetDescripcion(): void
    {
        $descripcion = 'Organización dedicada a ayudar';
        $this->organizacion->setDescripcion($descripcion);
        $this->assertEquals($descripcion, $this->organizacion->getDescripcion());
    }

    public function testDescripcionPuedeSerNula(): void
    {
        $this->organizacion->setDescripcion(null);
        $this->assertNull($this->organizacion->getDescripcion());
    }

    public function testSetYGetDireccion(): void
    {
        $direccion = 'Calle Falsa 123';
        $this->organizacion->setDireccion($direccion);
        $this->assertEquals($direccion, $this->organizacion->getDireccion());
    }

    public function testDireccionPuedeSerNula(): void
    {
        $this->organizacion->setDireccion(null);
        $this->assertNull($this->organizacion->getDireccion());
    }

    public function testSetYGetSitioWeb(): void
    {
        $sitio = 'https://ejemplo.org';
        $this->organizacion->setSitioWeb($sitio);
        $this->assertEquals($sitio, $this->organizacion->getSitioWeb());
    }

    public function testSitioWebPuedeSerNulo(): void
    {
        $this->organizacion->setSitioWeb(null);
        $this->assertNull($this->organizacion->getSitioWeb());
    }

    public function testSetYGetTelefono(): void
    {
        $telefono = '612345678';
        $this->organizacion->setTelefono($telefono);
        $this->assertEquals($telefono, $this->organizacion->getTelefono());
    }

    public function testTelefonoPuedeSerNulo(): void
    {
        $this->organizacion->setTelefono(null);
        $this->assertNull($this->organizacion->getTelefono());
    }

    // ========================================================================
    // TESTS DE RELACIÓN USUARIO
    // ========================================================================

    public function testUsuarioInicialmenteNulo(): void
    {
        $this->assertNull($this->organizacion->getUsuario());
    }

    public function testSetYGetUsuario(): void
    {
        $usuario = $this->createMock(Usuario::class);
        $usuario->method('getId')->willReturn(5);

        $this->organizacion->setUsuario($usuario);
        $this->assertSame($usuario, $this->organizacion->getUsuario());
        $this->assertEquals(5, $this->organizacion->getId());
    }

    // ========================================================================
    // TESTS DE TIMESTAMPS
    // ========================================================================

    public function testUpdatedAtInicialmenteNulo(): void
    {
        $this->assertNull($this->organizacion->getUpdatedAt());
    }

    public function testSetYGetUpdatedAt(): void
    {
        $fecha = new \DateTime();
        $this->organizacion->setUpdatedAt($fecha);
        $this->assertEquals($fecha, $this->organizacion->getUpdatedAt());
    }

    // ========================================================================
    // TESTS FLUENT INTERFACE
    // ========================================================================

    public function testFluentInterface(): void
    {
        $result = $this->organizacion
            ->setNombre('Test')
            ->setCif('G12345678')
            ->setDescripcion('Descripción')
            ->setTelefono('612345678');

        $this->assertSame($this->organizacion, $result);
    }
}
