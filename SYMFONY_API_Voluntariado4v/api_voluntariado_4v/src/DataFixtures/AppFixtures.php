<?php

namespace App\DataFixtures;

use App\Entity\Rol;
use App\Entity\Usuario;
use App\Entity\Voluntario;
use App\Entity\Organizacion;
use App\Entity\Coordinador;
use App\Entity\Curso;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // ==========================================
        // 1. ROLES (IDs fijos según tu SQL)
        // ==========================================
        $roles = [];
        $nombresRoles = ['Administrador', 'Voluntario', 'Organizacion', 'Coordinador'];

        foreach ($nombresRoles as $nombre) {
            $rol = new Rol();
            $rol->setNombre($nombre);
            $manager->persist($rol);
            $roles[$nombre] = $rol; // Guardamos referencia para usarla luego
        }
        $manager->flush(); // Guardar roles primero

        // ==========================================
        // 2. CURSOS
        // ==========================================
        $cursoDam = new Curso();
        $cursoDam->setNombre('Desarrollo de Apps Multiplataforma');
        $cursoDam->setAbreviacion('DAM');
        $cursoDam->setGrado('Grado Superior');
        $cursoDam->setNivel(2);
        $manager->persist($cursoDam);
        $manager->flush();

        // ==========================================
        // 3. COORDINADOR (El "Jefe")
        // ==========================================
        // NOTA: Para que el login de Google funcione directo, 

        $userAdmin = new Usuario();
        $userAdmin->setCorreo('maitesolam@gmail.com');
        $userAdmin->setGoogleId('sGVnRsLbQgPQE0ouMxAdT21C1J02');
        $userAdmin->setRol($roles['Coordinador']);
        $userAdmin->setEstadoCuenta('Activa');
        $manager->persist($userAdmin);
        $manager->flush();

        $coord = new Coordinador();
        $coord->setUsuario($userAdmin);
        $coord->setNombre('Maite');
        $coord->setApellidos('Sola(Admin)');
        $manager->persist($coord);

        // ==========================================
        // 4. VOLUNTARIO ("Pepe")
        // ==========================================
        $userVol = new Usuario();
        $userVol->setCorreo('pepe@test.com');
        $userVol->setGoogleId('uid_voluntario_test');
        $userVol->setRol($roles['Voluntario']);
        $userVol->setEstadoCuenta('Activa');
        $manager->persist($userVol);
        $manager->flush();

        $vol = new Voluntario();
        $vol->setUsuario($userVol);
        $vol->setNombre('Pepe');
        $vol->setApellidos('Ejemplo');
        $vol->setDni('12345678A');
        $vol->setCursoActual($cursoDam);
        $manager->persist($vol);

        // ==========================================
        // 5. ORGANIZACIÓN ("Ayuda Global")
        // ==========================================
        $userOrg = new Usuario();
        $userOrg->setCorreo('contacto@ong.com');
        $userOrg->setGoogleId('uid_organizacion_test');
        $userOrg->setRol($roles['Organizacion']);
        $userOrg->setEstadoCuenta('Activa');
        $manager->persist($userOrg);
        $manager->flush();

        $org = new Organizacion();
        $org->setUsuario($userOrg);
        $org->setNombre('Ayuda Global ONG');
        $org->setCif('G11223344');
        $manager->persist($org);

        // Guardar todo lo pendiente
        $manager->flush();
    }
}
