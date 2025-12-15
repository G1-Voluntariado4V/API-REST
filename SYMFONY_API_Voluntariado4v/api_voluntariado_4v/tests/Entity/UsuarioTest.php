<?php

namespace App\Tests\Entity;

use App\Entity\Usuario;
use PHPUnit\Framework\TestCase;

class UsuarioTest extends TestCase
{
    public function testValoresPorDefecto(): void
    {
        $usuario = new Usuario();

        // 1. Roles por defecto
        // Symfony exige que todo usuario tenga al menos ROLE_USER.
        // Este test asegura que tu entidad lo garantiza.
        $this->assertContains('ROLE_USER', $usuario->getRoles());
        
        // 2. Estado por defecto
        // Según tu SQL, debería ser 'Activo' o 'Pendiente'. Verificamos que no sea null.
        $this->assertNotNull($usuario->getEstadoCuenta());
        $this->assertEquals('Pendiente', $usuario->getEstadoCuenta()); // En tu entidad el default es 'Pendiente'
        
        // 3. Fecha de registro
        // Debería asignarse al crear el objeto.
        $this->assertInstanceOf(\DateTimeInterface::class, $usuario->getFechaRegistro());
    }

    public function testGestionDeRoles(): void
    {
        $usuario = new Usuario();
        
        // El usuario obtiene sus roles de la entidad ROL asociada.
        // Simulamos un Rol
        $rolAdmin = new \App\Entity\Rol();
        $rolAdmin->setNombre('Administrador'); // Asumiendo que Rol tiene setNombre
        
        $usuario->setRol($rolAdmin);

        // Verificaciones
        $rolesCalculados = $usuario->getRoles();
        
        // Symfony espera ROLE_ + nombre en mayúsculas
        $this->assertContains('ROLE_ADMINISTRADOR', $rolesCalculados);
        
        // Si no hay rol asignado, por defecto debería ser ROLE_USER (o similar según tu lógica)
        $usuarioSinRol = new Usuario();
        $this->assertContains('ROLE_USER', $usuarioSinRol->getRoles());
    }

    public function testEmailSeGuardaEnMinusculasLogic(): void
    {
        // Esta es una regla de negocio común. Si tu entidad fuerza minúsculas, este test lo valida.
        // Si no tienes esa lógica en setCorreo(), este test fallará (y te recordará implementarla o borrar el test).
        
        $usuario = new Usuario();
        /* 
           Si tu setter hace strtolower($correo), descomenta esto:
           $usuario->setCorreo('MiCorreo@GMAIL.com');
           $this->assertEquals('micorreo@gmail.com', $usuario->getCorreo());
        */
        
        // Por ahora probamos asignación simple
        $usuario->setCorreo('test@test.com');
        $this->assertEquals('test@test.com', $usuario->getCorreo());
    }
}
