<?php

namespace App\DataFixtures;

use App\Entity\Coordinador;
use App\Entity\Curso;
use App\Entity\Idioma;
use App\Entity\ODS;
use App\Entity\Organizacion;
use App\Entity\Rol;
use App\Entity\TipoVoluntariado;
use App\Entity\Usuario;
use App\Entity\Voluntario;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface; // <--- 1. Importante

class AppFixtures extends Fixture
{
    // 2. Inyectamos el servicio de hash
    private UserPasswordHasherInterface $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        // ======================================================
        // 1. ROLES
        // ======================================================
        $rolesCache = [];
        $nombresRoles = ['Administrador', 'Voluntario', 'Organizacion', 'Coordinador'];

        foreach ($nombresRoles as $nombre) {
            $rol = new Rol();
            $rol->setNombre($nombre); // Asegúrate de que en Rol.php es setNombre() y no setNombreRol()
            $manager->persist($rol);
            $rolesCache[$nombre] = $rol;
        }

        // ======================================================
        // 2. IDIOMAS
        // ======================================================
        $idiomasData = [
            ['Español', 'ES'],
            ['Inglés', 'EN'],
            ['Francés', 'FR'],
            ['Alemán', 'DE']
        ];

        foreach ($idiomasData as $data) {
            $idioma = new Idioma();
            $idioma->setNombre($data[0]);
            $idioma->setCodigoIso($data[1]);
            $manager->persist($idioma);
        }

        // ======================================================
        // 3. TIPOS DE VOLUNTARIADO
        // ======================================================
        $tiposData = ['Medioambiente', 'Acción Social', 'Educación', 'Protección Animal', 'Salud'];

        foreach ($tiposData as $nombreTipo) {
            $tipo = new TipoVoluntariado();
            $tipo->setNombreTipo($nombreTipo);
            $manager->persist($tipo);
        }

        // ======================================================
        // 4. ODS
        // ======================================================
        $odsData = [
            [1, 'Fin de la Pobreza', 'Poner fin a la pobreza...'],
            [2, 'Hambre Cero', 'Poner fin al hambre...'],
            [4, 'Educación de Calidad', 'Garantizar educación inclusiva...'],
            [13, 'Acción por el Clima', 'Adoptar medidas urgentes...']
        ];

        foreach ($odsData as $data) {
            $ods = new ODS($data[0], $data[1]);
            $ods->setDescripcion($data[2]);
            $manager->persist($ods);
        }

        // ======================================================
        // 5. CURSOS
        // ======================================================
        $cursosCache = [];
        $cursosData = [
            ['Desarrollo de Aplicaciones Web', 'DAW', 'Grado Superior', 2],
            ['Desarrollo de Apps Multiplataforma', 'DAM', 'Grado Superior', 2],
            ['Administración y Finanzas', 'ADFIN', 'Grado Superior', 2],
            ['Cuidados Auxiliares de Enfermería', 'CAE', 'Grado Medio', 1]
        ];

        foreach ($cursosData as $data) {
            $curso = new Curso();
            $curso->setNombre($data[0]);
            $curso->setAbreviacion($data[1]);
            $curso->setGrado($data[2]);
            $curso->setNivel($data[3]);
            $manager->persist($curso);
            $cursosCache[$data[1]] = $curso;
        }

        $manager->flush();

        // ======================================================
        // 6. USUARIOS Y PERFILES
        // ======================================================

        // --- 6.1 COORDINADOR (Maite) ---
        $userAdmin = new Usuario();
        $userAdmin->setCorreo('maitesolam@gmail.com');
        $userAdmin->setGoogleId('sGVnRsLbQgPQE0ouMxAdT21C1J02');
        $userAdmin->setRol($rolesCache['Coordinador']);
        $userAdmin->setEstadoCuenta('Activa');
        // 3. Ponemos contraseña (aunque usen Google, el campo es obligatorio en BD)
        $userAdmin->setPassword($this->hasher->hashPassword($userAdmin, 'password123'));
        $manager->persist($userAdmin);

        $coord = new Coordinador();
        $coord->setUsuario($userAdmin);
        $coord->setNombre('Maite');
        $coord->setApellidos('Sola (Admin)');
        $coord->setTelefono('600123456');
        $manager->persist($coord);

        // --- 6.2 VOLUNTARIO (Pepe) ---
        $userVol = new Usuario();
        $userVol->setCorreo('pepe@test.com');
        $userVol->setGoogleId('uid_voluntario_test');
        $userVol->setRol($rolesCache['Voluntario']);
        $userVol->setEstadoCuenta('Activa');
        $userVol->setPassword($this->hasher->hashPassword($userVol, 'password123')); // <--- Contraseña obligatoria
        $manager->persist($userVol);

        $vol = new Voluntario();
        $vol->setUsuario($userVol);
        $vol->setNombre('Pepe');
        $vol->setApellidos('Ejemplo');
        $vol->setDni('12345678A');
        $vol->setTelefono('611223344');
        if (isset($cursosCache['DAM'])) {
            $vol->setCursoActual($cursosCache['DAM']);
        }
        $manager->persist($vol);

        // --- 6.3 ORGANIZACIÓN (Ayuda Global) ---
        $userOrg = new Usuario();
        $userOrg->setCorreo('contacto@ong.com');
        $userOrg->setGoogleId('uid_organizacion_test');
        $userOrg->setRol($rolesCache['Organizacion']);
        $userOrg->setEstadoCuenta('Activa');
        $userOrg->setPassword($this->hasher->hashPassword($userOrg, 'password123')); // <--- Contraseña obligatoria
        $manager->persist($userOrg);

        $org = new Organizacion();
        $org->setUsuario($userOrg);
        $org->setNombre('Ayuda Global ONG');
        $org->setCif('G11223344');
        $org->setDescripcion('Ayudando al mundo desde 2024');
        $org->setTelefono('948000000');
        $manager->persist($org);

        $manager->flush();
    }
}
