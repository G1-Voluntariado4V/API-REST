<?php

namespace App\Tests\DTO;

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
    // TESTS DE OrganizacionUpdateDTO
    // ========================================================================

    public function testOrganizacionUpdateDTOValido(): void
    {
        $dto = new OrganizacionUpdateDTO(
            nombre: 'ONG Ejemplo',
            descripcion: 'Descripción de la organización',
            sitioWeb: 'https://ejemplo.org',
            direccion: 'Calle Falsa 123',
            telefono: '+34 91 123 45 67'
        );

        $violations = $this->validator->validate($dto);
        $this->assertCount(0, $violations);
    }

    public function testOrganizacionUpdateDTONombreVacio(): void
    {
        $dto = new OrganizacionUpdateDTO(
            nombre: '',
            descripcion: 'Descripción',
            sitioWeb: null,
            direccion: null,
            telefono: null
        );

        $violations = $this->validator->validate($dto);
        $this->assertGreaterThan(0, count($violations));
    }

    public function testOrganizacionUpdateDTONombreCorto(): void
    {
        $dto = new OrganizacionUpdateDTO(
            nombre: 'A',
            descripcion: 'Descripción',
            sitioWeb: null,
            direccion: null,
            telefono: null
        );

        $violations = $this->validator->validate($dto);
        $this->assertGreaterThan(0, count($violations));
    }

    public function testOrganizacionUpdateDTODescripcionVacia(): void
    {
        $dto = new OrganizacionUpdateDTO(
            nombre: 'ONG Ejemplo',
            descripcion: '',
            sitioWeb: null,
            direccion: null,
            telefono: null
        );

        $violations = $this->validator->validate($dto);
        $this->assertGreaterThan(0, count($violations));
    }

    public function testOrganizacionUpdateDTOTelefonoInvalido(): void
    {
        $dto = new OrganizacionUpdateDTO(
            nombre: 'ONG Ejemplo',
            descripcion: 'Descripción',
            sitioWeb: null,
            direccion: null,
            telefono: 'telefono-invalido'
        );

        $violations = $this->validator->validate($dto);
        $this->assertGreaterThan(0, count($violations));
    }

    // ========================================================================
    // TESTS DE PROPIEDADES
    // ========================================================================

    public function testOrganizacionUpdateDTOPropiedadesAccesibles(): void
    {
        $dto = new OrganizacionUpdateDTO(
            nombre: 'Test ONG',
            descripcion: 'Descripción Test',
            sitioWeb: 'https://test.org',
            direccion: 'Calle Test 123',
            telefono: '+34 600 11 22 33'
        );

        $this->assertEquals('Test ONG', $dto->nombre);
        $this->assertEquals('Descripción Test', $dto->descripcion);
        $this->assertEquals('https://test.org', $dto->sitioWeb);
        $this->assertEquals('Calle Test 123', $dto->direccion);
        $this->assertEquals('+34 600 11 22 33', $dto->telefono);
    }

    public function testOrganizacionUpdateDTOPropiedadesOpcionales(): void
    {
        $dto = new OrganizacionUpdateDTO(
            nombre: 'Test ONG',
            descripcion: 'Descripción',
            sitioWeb: null,
            direccion: null,
            telefono: null
        );

        $this->assertNull($dto->sitioWeb);
        $this->assertNull($dto->direccion);
        $this->assertNull($dto->telefono);
    }
}
