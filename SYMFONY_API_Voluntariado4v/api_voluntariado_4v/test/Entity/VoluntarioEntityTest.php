<?php

namespace App\Tests\Entity;

use App\Entity\Voluntario;
use App\Entity\Usuario;
use App\Entity\Curso;
use PHPUnit\Framework\TestCase;

class VoluntarioEntityTest extends TestCase
{
    public function testCrearVoluntario(): void
    {
        $voluntario = new Voluntario();
        $this->assertInstanceOf(Voluntario::class, $voluntario);
    }

    public function testSettersYGetters(): void
    {
        $voluntario = new Voluntario();
        $voluntario->setDni('12345678X');
        $voluntario->setTelefono('600123456');
        $voluntario->setCarnetConducir(true);
        $fecha = new \DateTime('2000-01-01');
        $voluntario->setFechaNac($fecha);

        $this->assertEquals('12345678X', $voluntario->getDni());
        $this->assertEquals('600123456', $voluntario->getTelefono());
        $this->assertTrue($voluntario->isCarnetConducir());
        $this->assertEquals($fecha, $voluntario->getFechaNac());
    }

    public function testRelaciones(): void
    {
        $voluntario = new Voluntario();

        // Usuario
        $usuario = new Usuario();
        $voluntario->setUsuario($usuario);
        $this->assertSame($usuario, $voluntario->getUsuario());

        // Curso
        $curso = new Curso();
        $voluntario->setCursoActual($curso);
        $this->assertSame($curso, $voluntario->getCursoActual());
    }
}
