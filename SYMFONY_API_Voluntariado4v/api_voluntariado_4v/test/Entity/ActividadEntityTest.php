<?php

namespace App\Tests\Entity;

use App\Entity\Actividad;
use App\Entity\Organizacion;
use PHPUnit\Framework\TestCase;

class ActividadEntityTest extends TestCase
{
    public function testCrearActividad(): void
    {
        $actividad = new Actividad();

        $actividad->setTitulo('Limpieza de Playa');
        $actividad->setDescripcion('Actividad medioambiental');
        $actividad->setCupoMaximo(50);
        $actividad->setDuracionHoras(4);

        $this->assertEquals('Limpieza de Playa', $actividad->getTitulo());
        $this->assertEquals(50, $actividad->getCupoMaximo());
        $this->assertEquals(4, $actividad->getDuracionHoras());
        // Default
        $this->assertEquals('En revision', $actividad->getEstadoPublicacion());
    }

    public function testRelacionOrganizacion(): void
    {
        $actividad = new Actividad();
        $org = new Organizacion();

        $actividad->setOrganizacion($org);
        $this->assertSame($org, $actividad->getOrganizacion());
    }
}
