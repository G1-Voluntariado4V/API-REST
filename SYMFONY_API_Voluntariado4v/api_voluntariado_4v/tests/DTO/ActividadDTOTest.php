<?php

namespace App\Tests\DTO;

use App\Model\Actividad\ActividadCreateDTO;
use App\Model\Actividad\ActividadUpdateDTO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Tests unitarios para los DTOs de Actividad
 */
class ActividadDTOTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    // ========================================================================
    // TESTS DE ActividadCreateDTO
    // ========================================================================

    public function testActividadCreateDTOValido(): void
    {
        $dto = new ActividadCreateDTO(
            titulo: 'Limpieza de Playa',
            descripcion: 'Actividad de voluntariado ambiental',
            fecha_inicio: '2026-06-15 09:00:00',
            duracion_horas: 4,
            cupo_maximo: 50,
            ubicacion: 'Playa de la Barceloneta',
            id_organizacion: 5,
            odsIds: [13, 14],
            tiposIds: [1]
        );

        $violations = $this->validator->validate($dto);
        $this->assertCount(0, $violations);
    }

    public function testActividadCreateDTOTituloVacio(): void
    {
        $dto = new ActividadCreateDTO(
            titulo: '',
            descripcion: 'Descripción',
            fecha_inicio: '2026-06-15 09:00:00',
            duracion_horas: 4,
            cupo_maximo: 50,
            ubicacion: 'Ubicación',
            id_organizacion: 5,
            tiposIds: [1]
        );

        $violations = $this->validator->validate($dto);
        $this->assertGreaterThan(0, count($violations));
    }

    public function testActividadCreateDTODuracionNegativa(): void
    {
        $dto = new ActividadCreateDTO(
            titulo: 'Título válido',
            descripcion: 'Descripción',
            fecha_inicio: '2026-06-15 09:00:00',
            duracion_horas: -1,
            cupo_maximo: 50,
            ubicacion: 'Ubicación',
            id_organizacion: 5,
            tiposIds: [1]
        );

        $violations = $this->validator->validate($dto);
        $this->assertGreaterThan(0, count($violations));
    }

    public function testActividadCreateDTOCupoNegativo(): void
    {
        $dto = new ActividadCreateDTO(
            titulo: 'Título válido',
            descripcion: 'Descripción',
            fecha_inicio: '2026-06-15 09:00:00',
            duracion_horas: 4,
            cupo_maximo: -10,
            ubicacion: 'Ubicación',
            id_organizacion: 5,
            tiposIds: [1]
        );

        $violations = $this->validator->validate($dto);
        $this->assertGreaterThan(0, count($violations));
    }

    public function testActividadCreateDTOSinTipos(): void
    {
        $dto = new ActividadCreateDTO(
            titulo: 'Título válido',
            descripcion: 'Descripción',
            fecha_inicio: '2026-06-15 09:00:00',
            duracion_horas: 4,
            cupo_maximo: 50,
            ubicacion: 'Ubicación',
            id_organizacion: 5,
            tiposIds: []
        );

        $violations = $this->validator->validate($dto);
        $this->assertGreaterThan(0, count($violations));
    }

    // ========================================================================
    // TESTS DE ActividadUpdateDTO
    // ========================================================================

    public function testActividadUpdateDTOValido(): void
    {
        $dto = new ActividadUpdateDTO(
            titulo: 'Título Actualizado',
            descripcion: 'Nueva descripción',
            ubicacion: 'Nueva ubicación',
            fecha_inicio: '2026-06-20 10:00:00',
            duracion_horas: 5,
            cupo_maximo: 60,
            odsIds: [13],
            tiposIds: [1, 2]
        );

        $violations = $this->validator->validate($dto);
        $this->assertCount(0, $violations);
    }

    public function testActividadUpdateDTOTituloCorto(): void
    {
        $dto = new ActividadUpdateDTO(
            titulo: '123',
            descripcion: 'Descripción',
            ubicacion: 'Ubicación',
            fecha_inicio: '2026-06-20 10:00:00',
            duracion_horas: 5,
            cupo_maximo: 60,
            tiposIds: [1]
        );

        $violations = $this->validator->validate($dto);
        $this->assertGreaterThan(0, count($violations));
    }

    // ========================================================================
    // TESTS DE PROPIEDADES
    // ========================================================================

    public function testActividadCreateDTOPropiedadesAccesibles(): void
    {
        $dto = new ActividadCreateDTO(
            titulo: 'Test',
            descripcion: 'Descripción Test',
            fecha_inicio: '2026-06-15 09:00:00',
            duracion_horas: 4,
            cupo_maximo: 50,
            ubicacion: 'Test Location',
            id_organizacion: 5,
            odsIds: [13, 14],
            tiposIds: [1]
        );

        $this->assertEquals('Test', $dto->titulo);
        $this->assertEquals('Descripción Test', $dto->descripcion);
        $this->assertEquals(4, $dto->duracion_horas);
        $this->assertEquals(50, $dto->cupo_maximo);
        $this->assertEquals(5, $dto->id_organizacion);
        $this->assertCount(2, $dto->odsIds);
        $this->assertCount(1, $dto->tiposIds);
    }
}
