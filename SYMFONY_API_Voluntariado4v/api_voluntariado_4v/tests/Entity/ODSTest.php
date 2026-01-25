<?php

namespace App\Tests\Entity;

use App\Entity\ODS;
use PHPUnit\Framework\TestCase;

class ODSTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $ods = new ODS();
        $ods->setId(1);
        $ods->setNombre('Test ODS');
        $ods->setDescripcion('Desc');
        $ods->setImgOds('imagen.jpg');

        $this->assertEquals(1, $ods->getId());
        $this->assertEquals('Test ODS', $ods->getNombre());
        $this->assertEquals('Desc', $ods->getDescripcion());
        $this->assertEquals('imagen.jpg', $ods->getImgOds());
    }

    public function testGetImgUrl(): void
    {
        $ods = new ODS();
        $this->assertNull($ods->getImgUrl());

        $ods->setImgOds('test.jpg');
        $this->assertEquals('/uploads/ods/test.jpg', $ods->getImgUrl());
    }
}
