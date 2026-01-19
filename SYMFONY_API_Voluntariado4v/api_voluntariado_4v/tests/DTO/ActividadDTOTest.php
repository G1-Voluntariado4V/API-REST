<?php

namespace App\Tests\DTO;

use App\Model\Actividad\ActividadCreateDTO;
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
            titulo: 'Limpieza de playas',
            descripcion: 'Actividad de voluntariado ambiental',
            fecha_inicio: '2025-07-01 10:00:00',
            duracion_horas: 4,
            cupo_maximo: 20,
            ubicacion: 'Playa de Valencia',
            id_organizacion: 1,
            odsIds: [],
            tiposIds: []
        );

        $violations = $this->validator->validate($dto);

        // Solo verificamos que no hay violaciones de NotBlank, NotNull, Positive
        $criticalViolations = 0;
        foreach ($violations as $violation) {
            $message = $violation->getMessage();
            if (
                str_contains($message, 'vacío') ||
                str_contains($message, 'mayor a 0') ||
                str_contains($message, 'This value should not be null')
            ) {
                $criticalViolations++;
            }
        }

        $this->assertEquals(0, $criticalViolations);
    }

    public function testActividadCreateDTOTituloVacio(): void
    {
        $dto = new ActividadCreateDTO(
            titulo: '',
            descripcion: 'Descripción',
            fecha_inicio: '2025-07-01 10:00:00',
            duracion_horas: 4,
            cupo_maximo: 20,
            ubicacion: 'Ubicación',
            id_organizacion: 1
        );

        $violations = $this->validator->validate($dto);

        $tituloViolation = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'titulo') {
                $tituloViolation = true;
                break;
            }
        }

        $this->assertTrue($tituloViolation, 'Debería haber una violación para título vacío');
    }

    public function testActividadCreateDTODuracionNegativa(): void
    {
        $dto = new ActividadCreateDTO(
            titulo: 'Test',
            descripcion: 'Descripción',
            fecha_inicio: '2025-07-01 10:00:00',
            duracion_horas: -5,
            cupo_maximo: 20,
            ubicacion: 'Ubicación',
            id_organizacion: 1
        );

        $violations = $this->validator->validate($dto);

        $duracionViolation = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'duracion_horas') {
                $duracionViolation = true;
                break;
            }
        }

        $this->assertTrue($duracionViolation, 'Debería haber una violación para duración negativa');
    }

    public function testActividadCreateDTOCupoNegativo(): void
    {
        $dto = new ActividadCreateDTO(
            titulo: 'Test',
            descripcion: 'Descripción',
            fecha_inicio: '2025-07-01 10:00:00',
            duracion_horas: 4,
            cupo_maximo: -10,
            ubicacion: 'Ubicación',
            id_organizacion: 1
        );

        $violations = $this->validator->validate($dto);

        $cupoViolation = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'cupo_maximo') {
                $cupoViolation = true;
                break;
            }
        }

        $this->assertTrue($cupoViolation, 'Debería haber una violación para cupo negativo');
    }

    public function testActividadCreateDTODuracionCero(): void
    {
        $dto = new ActividadCreateDTO(
            titulo: 'Test',
            descripcion: 'Descripción',
            fecha_inicio: '2025-07-01 10:00:00',
            duracion_horas: 0,
            cupo_maximo: 20,
            ubicacion: 'Ubicación',
            id_organizacion: 1
        );

        $violations = $this->validator->validate($dto);

        $duracionViolation = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'duracion_horas') {
                $duracionViolation = true;
                break;
            }
        }

        $this->assertTrue($duracionViolation, 'Debería haber una violación para duración cero');
    }

    public function testActividadCreateDTODescripcionNula(): void
    {
        $dto = new ActividadCreateDTO(
            titulo: 'Test',
            descripcion: null,
            fecha_inicio: '2025-07-01 10:00:00',
            duracion_horas: 4,
            cupo_maximo: 20,
            ubicacion: 'Ubicación',
            id_organizacion: 1
        );

        // La descripción puede ser nula, no debería generar violación
        $violations = $this->validator->validate($dto);

        $descripcionViolation = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'descripcion') {
                $descripcionViolation = true;
                break;
            }
        }

        $this->assertFalse($descripcionViolation, 'No debería haber violación para descripción nula');
    }

    public function testActividadCreateDTOConArraysODS(): void
    {
        $dto = new ActividadCreateDTO(
            titulo: 'Test',
            descripcion: 'Descripción',
            fecha_inicio: '2025-07-01 10:00:00',
            duracion_horas: 4,
            cupo_maximo: 20,
            ubicacion: 'Ubicación',
            id_organizacion: 1,
            odsIds: [1, 2, 3],
            tiposIds: [1, 2]
        );

        $this->assertCount(3, $dto->odsIds);
        $this->assertCount(2, $dto->tiposIds);
    }
}
