<?php

namespace App\Tests\Entity;

use App\Entity\Actividad;
use App\Entity\Organizacion;
use App\Entity\Usuario;
use PHPUnit\Framework\TestCase;

class OrganizacionTest extends TestCase
{
    // CÓDIGO ELIMINADO: testInicializacionColecciones y testAddRemoveActividadLogic
    // MOTIVO: La entidad Organizacion es el lado INVERSO y NO PROPIETARIO de la relación.
    // No tiene una propiedad $actividades mapeada con @OneToMany en el código PHP.
    // Aunque la BBDD tenga la FK en Actividad, el objeto Organizacion no tiene la lista en memoria.
    // Por tanto, no se puede testear addActividad/getActividades porque no existen.

    public function testDatosBasicosOrganizacion(): void
    {
        $org = new Organizacion();
        $org->setNombre("ONG Salvando Huellas");
        $org->setCif("G12345678");
        $org->setImgPerfil("logo.png");
        $org->setSitioWeb("https://test.com");

        $this->assertEquals("ONG Salvando Huellas", $org->getNombre());
        $this->assertEquals("G12345678", $org->getCif());
        $this->assertEquals("logo.png", $org->getImgPerfil());
    }

    public function testRelacionConUsuarioPadre(): void
    {
        // Como Organizacion hereda o se relaciona 1:1 con Usuario (dependiendo de tu diseño)
        // probamos la vinculación básica.
        $usuario = new Usuario();
        $org = new Organizacion();
        
        $org->setUsuario($usuario);
        
        $this->assertSame($usuario, $org->getUsuario());
    }
}
