<?php

namespace App\Tests\Entity;

use App\Entity\Idioma;
use App\Entity\Voluntario;
use App\Entity\VoluntarioIdioma;
use PHPUnit\Framework\TestCase;

class VoluntarioIdiomaTest extends TestCase
{
    public function testEntidadVinculacion(): void
    {
        $vi = new VoluntarioIdioma();
        $vi->setNivel('B2');

        $this->assertEquals('B2', $vi->getNivel());
    }

    public function testRelacionesObligatorias(): void
    {
        $vi = new VoluntarioIdioma();
        $voluntario = new Voluntario();
        $idioma = new Idioma();
        $idioma->setNombre('Francés');

        $vi->setVoluntario($voluntario);
        $vi->setIdioma($idioma);

        $this->assertSame($voluntario, $vi->getVoluntario());
        $this->assertSame($idioma, $vi->getIdioma());
        $this->assertEquals('Francés', $vi->getIdioma()->getNombre());
    }
}
