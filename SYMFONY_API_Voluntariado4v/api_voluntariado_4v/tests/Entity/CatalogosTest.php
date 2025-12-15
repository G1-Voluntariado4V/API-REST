<?php

namespace App\Tests\Entity;

use App\Entity\Curso;
use App\Entity\Idioma;
use App\Entity\ODS;
use App\Entity\Rol;
use App\Entity\TipoVoluntariado;
use PHPUnit\Framework\TestCase;

class CatalogosTest extends TestCase
{
    public function testEntidadRol(): void
    {
        $rol = new Rol();
        $rol->setNombre('Administrador');

        $this->assertEquals('Administrador', $rol->getNombre());
    }

    public function testEntidadCurso(): void
    {
        $curso = new Curso();
        $curso->setNombre('Desarrollo Web');
        $curso->setAbreviacion('DW');
        $curso->setGrado('Superior');
        $curso->setNivel(2);

        $this->assertEquals('Desarrollo Web', $curso->getNombre());
        $this->assertEquals('DW', $curso->getAbreviacion());
        $this->assertEquals('Superior', $curso->getGrado());
        $this->assertEquals(2, $curso->getNivel());
    }

    public function testEntidadIdioma(): void
    {
        $idioma = new Idioma();
        $idioma->setNombre('Inglés');
        
        $this->assertEquals('Inglés', $idioma->getNombre());
    }

    public function testEntidadODS(): void
    {
        // ODS tiene un constructor que requiere argumentos
        $ods = new ODS(1, 'Fin de la Pobreza');
        $ods->setDescripcion('Descripcion test');

        $this->assertEquals(1, $ods->getId());
        $this->assertEquals('Fin de la Pobreza', $ods->getNombre());
        $this->assertEquals('Descripcion test', $ods->getDescripcion());
    }

    public function testEntidadTipoVoluntariado(): void
    {
        $tipo = new TipoVoluntariado();
        $tipo->setNombreTipo('Presencial');
        
        $this->assertEquals('Presencial', $tipo->getNombreTipo());
    }
}
