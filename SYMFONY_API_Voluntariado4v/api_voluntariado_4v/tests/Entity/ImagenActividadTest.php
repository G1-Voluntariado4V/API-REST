<?php

namespace App\Tests\Entity;

use App\Entity\Actividad;
use App\Entity\ImagenActividad;
use PHPUnit\Framework\TestCase;

class ImagenActividadTest extends TestCase
{
    public function testEntidadSimple(): void
    {
        $imagen = new ImagenActividad();
        $imagen->setUrlImagen('https://example.com/foto.jpg');
        $imagen->setDescripcionPieFoto('Foto del evento');
        
        $this->assertEquals('https://example.com/foto.jpg', $imagen->getUrlImagen());
        $this->assertEquals('Foto del evento', $imagen->getDescripcionPieFoto());
    }

    public function testRelacionConActividad(): void
    {
        $imagen = new ImagenActividad();
        $actividad = new Actividad();

        $imagen->setActividad($actividad);

        $this->assertSame($actividad, $imagen->getActividad());
    }
}
