<?php

namespace App\Tests\DTO;

use App\Model\Organizacion\OrganizacionCreateDTO;
use App\Model\Organizacion\OrganizacionUpdateDTO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Tests unitarios para los DTOs de Organizacion
 */
class OrganizacionDTOTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    // ========================================================================
    // TESTS DE OrganizacionCreateDTO
    // ========================================================================

    public function testOrganizacionCreateDTOValido(): void
    {
        $dto = new OrganizacionCreateDTO(
            google_id: '987654321',
            correo: 'organizacion@test.com',
            nombre: 'ONG Solidaria',
            cif: 'B12345678',
            descripcion: 'Descripción de la ONG'
        );

        $violations = $this->validator->validate($dto);

        $criticalViolations = 0;
        foreach ($violations as $violation) {
            $message = $violation->getMessage();
            if (
                str_contains($message, 'obligatorio') ||
                str_contains($message, 'vacío') ||
                str_contains($message, 'blank')
            ) {
                $criticalViolations++;
            }
        }

        $this->assertEquals(0, $criticalViolations, 'No debería haber violaciones de campos obligatorios');
    }

    public function testOrganizacionCreateDTONombreVacio(): void
    {
        $dto = new OrganizacionCreateDTO(
            google_id: '987654321',
            correo: 'org@test.com',
            nombre: '',
            cif: 'B12345678',
            descripcion: 'Descripción'
        );

        $violations = $this->validator->validate($dto);

        $nombreViolation = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'nombre') {
                $nombreViolation = true;
                break;
            }
        }

        $this->assertTrue($nombreViolation, 'Debería haber una violación para nombre vacío');
    }

    public function testOrganizacionCreateDTOCifVacio(): void
    {
        $dto = new OrganizacionCreateDTO(
            google_id: '987654321',
            correo: 'org@test.com',
            nombre: 'ONG Test',
            cif: '',
            descripcion: 'Descripción'
        );

        $violations = $this->validator->validate($dto);

        $cifViolation = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'cif') {
                $cifViolation = true;
                break;
            }
        }

        $this->assertTrue($cifViolation, 'Debería haber una violación para CIF vacío');
    }

    public function testOrganizacionCreateDTODescripcionVacia(): void
    {
        $dto = new OrganizacionCreateDTO(
            google_id: '987654321',
            correo: 'org@test.com',
            nombre: 'ONG Test',
            cif: 'B12345678',
            descripcion: ''
        );

        $violations = $this->validator->validate($dto);

        $descripcionViolation = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'descripcion') {
                $descripcionViolation = true;
                break;
            }
        }

        $this->assertTrue($descripcionViolation, 'Debería haber una violación para descripción vacía');
    }

    public function testOrganizacionCreateDTOGoogleIdVacio(): void
    {
        $dto = new OrganizacionCreateDTO(
            google_id: '',
            correo: 'org@test.com',
            nombre: 'ONG Test',
            cif: 'B12345678',
            descripcion: 'Descripción'
        );

        $violations = $this->validator->validate($dto);

        $googleIdViolation = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'google_id') {
                $googleIdViolation = true;
                break;
            }
        }

        $this->assertTrue($googleIdViolation, 'Debería haber una violación para google_id vacío');
    }

    // ========================================================================
    // TESTS DE OrganizacionUpdateDTO
    // ========================================================================

    public function testOrganizacionUpdateDTOValido(): void
    {
        $dto = new OrganizacionUpdateDTO(
            nombre: 'ONG Actualizada',
            descripcion: 'Nueva descripción'
        );

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations, 'No debería haber violaciones');
    }

    public function testOrganizacionUpdateDTOConSitioWebValido(): void
    {
        $dto = new OrganizacionUpdateDTO(
            nombre: 'ONG Test',
            descripcion: 'Descripción',
            sitioWeb: 'https://www.ejemplo.org'
        );

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations, 'No debería haber violaciones con URL válida');
    }

    public function testOrganizacionUpdateDTONombreVacio(): void
    {
        $dto = new OrganizacionUpdateDTO(
            nombre: '',
            descripcion: 'Descripción'
        );

        $violations = $this->validator->validate($dto);

        $nombreViolation = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'nombre') {
                $nombreViolation = true;
                break;
            }
        }

        $this->assertTrue($nombreViolation, 'Debería haber una violación para nombre vacío');
    }

    // ========================================================================
    // TESTS DE PROPIEDADES
    // ========================================================================

    public function testOrganizacionCreateDTOPropiedadesAccesibles(): void
    {
        $dto = new OrganizacionCreateDTO(
            google_id: '987654321',
            correo: 'org@test.com',
            nombre: 'ONG Test',
            cif: 'B12345678',
            descripcion: 'Descripción de prueba',
            sitioWeb: 'https://test.org',
            direccion: 'Calle Test 123',
            telefono: '912345678'
        );

        $this->assertEquals('987654321', $dto->google_id);
        $this->assertEquals('org@test.com', $dto->correo);
        $this->assertEquals('ONG Test', $dto->nombre);
        $this->assertEquals('B12345678', $dto->cif);
        $this->assertEquals('Descripción de prueba', $dto->descripcion);
        $this->assertEquals('https://test.org', $dto->sitioWeb);
        $this->assertEquals('Calle Test 123', $dto->direccion);
        $this->assertEquals('912345678', $dto->telefono);
    }

    public function testOrganizacionUpdateDTOPropiedadesAccesibles(): void
    {
        $dto = new OrganizacionUpdateDTO(
            nombre: 'ONG Updated',
            descripcion: 'Descripción actualizada',
            sitioWeb: 'https://www.test.org',
            direccion: 'Nueva dirección',
            telefono: '912345678'
        );

        $this->assertEquals('ONG Updated', $dto->nombre);
        $this->assertEquals('Descripción actualizada', $dto->descripcion);
        $this->assertEquals('https://www.test.org', $dto->sitioWeb);
        $this->assertEquals('Nueva dirección', $dto->direccion);
        $this->assertEquals('912345678', $dto->telefono);
    }
}
