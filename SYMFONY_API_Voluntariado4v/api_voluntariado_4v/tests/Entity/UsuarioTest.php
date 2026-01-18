<?php

namespace App\Tests\Entity;

use App\Entity\Usuario;
use App\Entity\Rol;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para la entidad Usuario
 */
class UsuarioTest extends TestCase
{
    private Usuario $usuario;

    protected function setUp(): void
    {
        $this->usuario = new Usuario();
    }

    // ========================================================================
    // TESTS DE PROPIEDADES BÁSICAS
    // ========================================================================

    public function testIdInicialmenteNulo(): void
    {
        $this->assertNull($this->usuario->getId());
    }

    public function testSetGetCorreo(): void
    {
        $correo = 'test@example.com';
        $this->usuario->setCorreo($correo);
        $this->assertEquals($correo, $this->usuario->getCorreo());
    }

    public function testSetGetGoogleId(): void
    {
        $googleId = '123456789';
        $this->usuario->setGoogleId($googleId);
        $this->assertEquals($googleId, $this->usuario->getGoogleId());
    }

    public function testSetGetRefreshToken(): void
    {
        $token = 'abc123token';
        $this->usuario->setRefreshToken($token);
        $this->assertEquals($token, $this->usuario->getRefreshToken());
    }

    public function testRefreshTokenPuedeSerNulo(): void
    {
        $this->usuario->setRefreshToken(null);
        $this->assertNull($this->usuario->getRefreshToken());
    }

    // ========================================================================
    // TESTS DE ESTADO DE CUENTA
    // ========================================================================

    public function testEstadoCuentaDefectoPendiente(): void
    {
        $this->assertEquals('Pendiente', $this->usuario->getEstadoCuenta());
    }

    public function testSetEstadoCuentaActiva(): void
    {
        $this->usuario->setEstadoCuenta('Activa');
        $this->assertEquals('Activa', $this->usuario->getEstadoCuenta());
    }

    public function testSetEstadoCuentaBloqueada(): void
    {
        $this->usuario->setEstadoCuenta('Bloqueada');
        $this->assertEquals('Bloqueada', $this->usuario->getEstadoCuenta());
    }

    public function testSetEstadoCuentaRechazada(): void
    {
        $this->usuario->setEstadoCuenta('Rechazada');
        $this->assertEquals('Rechazada', $this->usuario->getEstadoCuenta());
    }

    // ========================================================================
    // TESTS DE RELACIÓN ROL
    // ========================================================================

    public function testRolInicialmenteNulo(): void
    {
        $this->assertNull($this->usuario->getRol());
    }

    public function testSetGetRol(): void
    {
        $rol = $this->createMock(Rol::class);
        $rol->method('getNombre')->willReturn('Voluntario');

        $this->usuario->setRol($rol);
        $this->assertSame($rol, $this->usuario->getRol());
    }

    public function testGetRolesConRolVoluntario(): void
    {
        $rol = $this->createMock(Rol::class);
        $rol->method('getNombre')->willReturn('Voluntario');

        $this->usuario->setRol($rol);
        $roles = $this->usuario->getRoles();

        $this->assertIsArray($roles);
        $this->assertContains('ROLE_VOLUNTARIO', $roles);
    }

    public function testGetRolesSinRolDevuelveRoleUser(): void
    {
        $roles = $this->usuario->getRoles();

        $this->assertIsArray($roles);
        $this->assertContains('ROLE_USER', $roles);
    }

    // ========================================================================
    // TESTS DE FECHAS
    // ========================================================================

    public function testFechaRegistroSeEstableceEnConstructor(): void
    {
        $this->assertInstanceOf(\DateTimeInterface::class, $this->usuario->getFechaRegistro());
    }

    public function testSetGetFechaRegistro(): void
    {
        $fecha = new \DateTime('2025-01-01');
        $this->usuario->setFechaRegistro($fecha);
        $this->assertEquals($fecha, $this->usuario->getFechaRegistro());
    }

    public function testUpdatedAtInicialmenteNulo(): void
    {
        $this->assertNull($this->usuario->getUpdatedAt());
    }

    public function testSetGetUpdatedAt(): void
    {
        $fecha = new \DateTime();
        $this->usuario->setUpdatedAt($fecha);
        $this->assertEquals($fecha, $this->usuario->getUpdatedAt());
    }

    public function testDeletedAtInicialmenteNulo(): void
    {
        $this->assertNull($this->usuario->getDeletedAt());
    }

    public function testSetGetDeletedAt(): void
    {
        $fecha = new \DateTimeImmutable();
        $this->usuario->setDeletedAt($fecha);
        $this->assertEquals($fecha, $this->usuario->getDeletedAt());
    }

    // ========================================================================
    // TESTS DE INTERFAZ USER
    // ========================================================================

    public function testGetUserIdentifierDevuelveCorreo(): void
    {
        $correo = 'identificador@test.com';
        $this->usuario->setCorreo($correo);
        $this->assertEquals($correo, $this->usuario->getUserIdentifier());
    }

    public function testToStringDevuelveCorreo(): void
    {
        $correo = 'string@test.com';
        $this->usuario->setCorreo($correo);
        $this->assertEquals($correo, (string) $this->usuario);
    }

    public function testEraseCredentialsNoLanzaExcepcion(): void
    {
        // Solo verificamos que no lance excepciones
        $this->usuario->eraseCredentials();
        $this->assertTrue(true);
    }

    // ========================================================================
    // TESTS DE FLUENT INTERFACE (Encadenamiento)
    // ========================================================================

    public function testSettersRetornanInstanciaDelMismoObjeto(): void
    {
        $result = $this->usuario->setCorreo('test@test.com');
        $this->assertSame($this->usuario, $result);

        $result = $this->usuario->setGoogleId('123');
        $this->assertSame($this->usuario, $result);

        $result = $this->usuario->setEstadoCuenta('Activa');
        $this->assertSame($this->usuario, $result);
    }
}
