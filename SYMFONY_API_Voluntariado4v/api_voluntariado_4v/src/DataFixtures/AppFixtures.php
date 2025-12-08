<?php

namespace App\DataFixtures;

use App\Entity\Actividad; // <--- Asegúrate de importar esto
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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
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
            $rol->setNombre($nombre);
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
            ['Alemán', 'DE'],
            ['Euskera', 'EU'] // Añadido extra
        ];

        foreach ($idiomasData as $data) {
            $idioma = new Idioma();
            $idioma->setNombre($data[0]);
            $idioma->setCodigoIso($data[1]);
            $manager->persist($idioma);
        }

        // ======================================================
        // 3. TIPOS DE VOLUNTARIADO (Listado Completo)
        // ======================================================
        $tiposCache = []; // Para usarlos luego en las actividades
        $tiposData = [
            'Medioambiente',
            'Acción Social',
            'Educación',
            'Protección Animal',
            'Salud / Sanitario',      // <--- Solicitado
            'Tecnológico / Digital',  // <--- Solicitado
            'Deportivo',              // <--- Solicitado
            'Cultural / Artístico',
            'Emergencias / Protección Civil',
            'Cooperación Internacional',
            'Ocio y Tiempo Libre',
            'Apoyo Administrativo'
        ];

        foreach ($tiposData as $nombreTipo) {
            $tipo = new TipoVoluntariado();
            $tipo->setNombreTipo($nombreTipo);
            $manager->persist($tipo);
            // Guardamos referencia para usarla abajo
            $tiposCache[$nombreTipo] = $tipo;
        }

        // ======================================================
        // 4. ODS
        // ======================================================
        $odsCache = []; // Para usarlos luego
        $odsData = [
            [1, 'Fin de la Pobreza', 'Poner fin a la pobreza...'],
            [2, 'Hambre Cero', 'Poner fin al hambre...'],
            [3, 'Salud y Bienestar', 'Garantizar una vida sana...'],
            [4, 'Educación de Calidad', 'Garantizar educación inclusiva...'],
            [5, 'Igualdad de Género', 'Lograr la igualdad entre los géneros...'],
            [10, 'Reducción de las Desigualdades', 'Reducir la desigualdad en y entre los países...'],
            [13, 'Acción por el Clima', 'Adoptar medidas urgentes...'],
            [14, 'Vida Submarina', 'Conservar los océanos...'],
            [15, 'Vida de Ecosistemas Terrestres', 'Proteger el uso sostenible...']
        ];

        foreach ($odsData as $data) {
            $ods = new ODS($data[0], $data[1]); // ID Manual, Nombre
            $ods->setDescripcion($data[2]);
            $manager->persist($ods);
            // Guardamos referencia por su ID real (1, 2, 13...)
            $odsCache[$data[0]] = $ods;
        }

        // ======================================================
        // 5. CURSOS
        // ======================================================
        $cursosCache = [];
        $cursosData = [
            ['Desarrollo de Aplicaciones Web', 'DAW', 'Grado Superior', 2],
            ['Desarrollo de Apps Multiplataforma', 'DAM', 'Grado Superior', 2],
            ['Administración y Finanzas', 'ADFIN', 'Grado Superior', 2],
            ['Cuidados Auxiliares de Enfermería', 'CAE', 'Grado Medio', 1],
            ['Marketing y Publicidad', 'MK', 'Grado Superior', 2]
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

        $manager->flush(); // Guardamos catálogos antes de seguir

        // ======================================================
        // 6. USUARIOS Y PERFILES
        // ======================================================

        // --- 6.1 COORDINADOR (Maite) ---
        $userAdmin = new Usuario();
        $userAdmin->setCorreo('maitesolam@gmail.com');
        $userAdmin->setGoogleId('sGVnRsLbQgPQE0ouMxAdT21C1J02');
        $userAdmin->setRol($rolesCache['Coordinador']);
        $userAdmin->setEstadoCuenta('Activa');
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
        $userVol->setPassword($this->hasher->hashPassword($userVol, 'password123'));
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

        // --- 6.3 ORGANIZACIÓN 1 (Ayuda Global) ---
        $userOrg = new Usuario();
        $userOrg->setCorreo('contacto@ong.com');
        $userOrg->setGoogleId('uid_organizacion_test');
        $userOrg->setRol($rolesCache['Organizacion']);
        $userOrg->setEstadoCuenta('Activa');
        $userOrg->setPassword($this->hasher->hashPassword($userOrg, 'password123'));
        $manager->persist($userOrg);

        $org1 = new Organizacion();
        $org1->setUsuario($userOrg);
        $org1->setNombre('Ayuda Global ONG');
        $org1->setCif('G11223344');
        $org1->setDescripcion('Ayudando al mundo desde 2024');
        $org1->setTelefono('948000000');
        $manager->persist($org1);

        // --- 6.4 ORGANIZACIÓN 2 (Tech For Good - Para probar voluntariado tecnológico) ---
        $userOrg2 = new Usuario();
        $userOrg2->setCorreo('info@techforgood.org');
        $userOrg2->setGoogleId('uid_org_tech');
        $userOrg2->setRol($rolesCache['Organizacion']);
        $userOrg2->setEstadoCuenta('Activa');
        $userOrg2->setPassword($this->hasher->hashPassword($userOrg2, 'password123'));
        $manager->persist($userOrg2);

        $org2 = new Organizacion();
        $org2->setUsuario($userOrg2);
        $org2->setNombre('Tech For Good');
        $org2->setCif('B99887766');
        $org2->setDescripcion('Tecnología para el cambio social.');
        $org2->setSitioWeb('https://techforgood.org');
        $manager->persist($org2);


        // ======================================================
        // 7. ACTIVIDADES (Usando los Caches)
        // ======================================================

        // ACTIVIDAD 1: MEDIOAMBIENTE
        $actividad1 = new Actividad();
        $actividad1->setOrganizacion($org1);
        $actividad1->setTitulo('Limpieza de Playa Norte');
        $actividad1->setDescripcion('Recogida de plásticos en la costa para proteger la fauna marina.');
        $actividad1->setUbicacion('Playa Norte, Valencia');
        $actividad1->setDuracionHoras(4);
        $actividad1->setCupoMaximo(20);
        $actividad1->setEstadoPublicacion('Publicada');
        $actividad1->setFechaInicio((new \DateTime())->modify('+2 days')->setTime(10, 0));

        // Relaciones M:N usando los caches
        if (isset($odsCache[13])) $actividad1->addOd($odsCache[13]); // Acción por el clima
        if (isset($odsCache[14])) $actividad1->addOd($odsCache[14]); // Vida Submarina
        if (isset($tiposCache['Medioambiente'])) $actividad1->addTiposVoluntariado($tiposCache['Medioambiente']);

        $manager->persist($actividad1);

        // ACTIVIDAD 2: TECNOLÓGICA (Taller para mayores)
        $actividad2 = new Actividad();
        $actividad2->setOrganizacion($org2);
        $actividad2->setTitulo('Alfabetización Digital para Mayores');
        $actividad2->setDescripcion('Enseñar a usar el smartphone y WhatsApp a personas de la tercera edad.');
        $actividad2->setUbicacion('Centro Cívico Centro');
        $actividad2->setDuracionHoras(2);
        $actividad2->setCupoMaximo(5);
        $actividad2->setEstadoPublicacion('Publicada');
        $actividad2->setFechaInicio((new \DateTime())->modify('+5 days')->setTime(17, 0));

        // Relaciones M:N
        if (isset($odsCache[4])) $actividad2->addOd($odsCache[4]); // Educación de Calidad
        if (isset($odsCache[10])) $actividad2->addOd($odsCache[10]); // Reducción de desigualdades
        if (isset($tiposCache['Tecnológico / Digital'])) $actividad2->addTiposVoluntariado($tiposCache['Tecnológico / Digital']);
        if (isset($tiposCache['Educación'])) $actividad2->addTiposVoluntariado($tiposCache['Educación']);

        $manager->persist($actividad2);

        $manager->flush();
    }
}
