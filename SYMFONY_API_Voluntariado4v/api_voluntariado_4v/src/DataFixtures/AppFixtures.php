<?php

namespace App\DataFixtures;

use App\Entity\Actividad;
use App\Entity\Coordinador;
use App\Entity\Curso;
use App\Entity\Idioma;
use App\Entity\Inscripcion;
use App\Entity\ODS;
use App\Entity\Organizacion;
use App\Entity\Rol;
use App\Entity\TipoVoluntariado;
use App\Entity\Usuario;
use App\Entity\Voluntario;
use App\Entity\VoluntarioIdioma;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
// 👇 Importaciones necesarias para forzar los IDs manuales
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // ======================================================
        // 1. ROLES
        // ======================================================
        $rolesCache = [];
        $nombresRoles = ['Coordinador', 'Voluntario', 'Organizacion'];

        foreach ($nombresRoles as $nombre) {
            $rol = new Rol();
            $rol->setNombre($nombre);
            $manager->persist($rol);
            $rolesCache[$nombre] = $rol;
        }

        // ======================================================
        // 2. IDIOMAS
        // ======================================================
        $idiomasCache = [];
        $idiomasData = [
            ['Español', 'ES'],
            ['Inglés', 'EN'],
            ['Francés', 'FR'],
            ['Alemán', 'DE'],
            ['Euskera', 'EU']
        ];

        foreach ($idiomasData as $data) {
            $idioma = new Idioma();
            $idioma->setNombre($data[0]);
            $idioma->setCodigoIso($data[1]);
            $manager->persist($idioma);
            $idiomasCache[$data[0]] = $idioma;
        }

        // ======================================================
        // 3. TIPOS DE VOLUNTARIADO
        // ======================================================
        $tiposCache = [];
        $tiposData = [
            'Medioambiente',
            'Acción Social',
            'Educación',
            'Protección Animal',
            'Salud / Sanitario',
            'Tecnológico / Digital',
            'Deportivo',
            'Cultural / Artístico',
            'Emergencias'
        ];

        foreach ($tiposData as $nombreTipo) {
            $tipo = new TipoVoluntariado();
            $tipo->setNombreTipo($nombreTipo);
            $manager->persist($tipo);
            $tiposCache[$nombreTipo] = $tipo;
        }

        // ======================================================
        // 4. ODS (CON IDs OBLIGATORIOS Y FIJOS)
        // ======================================================



        $odsNombreMap = []; // Cache para buscar por nombre luego
        $odsData = [
            [1, 'Fin de la Pobreza'],
            [2, 'Hambre Cero'],
            [3, 'Salud y Bienestar'],
            [4, 'Educación de Calidad'],
            [5, 'Igualdad de Género'],
            [6, 'Agua Limpia y Saneamiento'],
            [7, 'Energía Asequible y No Contaminante'],
            [8, 'Trabajo Decente y Crecimiento Económico'],
            [9, 'Industria, Innovación e Infraestructura'],
            [10, 'Reducción de las Desigualdades'],
            [11, 'Ciudades y Comunidades Sostenibles'],
            [12, 'Producción y Consumo Responsables'],
            [13, 'Acción por el Clima'],
            [14, 'Vida Submarina'],
            [15, 'Vida de Ecosistemas Terrestres'],
            [16, 'Paz, Justicia e Instituciones Sólidas'],
            [17, 'Alianzas para Lograr los Objetivos']
        ];

        foreach ($odsData as $data) {
            // 👇 AQUÍ USAMOS TU CONSTRUCTOR OBLIGATORIO (ID, NOMBRE)
            $ods = new ODS($data[0], $data[1]);

            // Si tienes setDescripcion, lo usamos
            if (method_exists($ods, 'setDescripcion')) {
                $ods->setDescripcion("Descripción oficial para el ODS " . $data[0]);
            }

            $manager->persist($ods);

            // Guardamos en caché por nombre para usarlos fácil en las actividades
            $odsNombreMap[$data[1]] = $ods;
        }

        // ======================================================
        // 5. CURSOS
        // ======================================================
        $cursosCache = [];
        $cursosData = [
            ['Desarrollo de Aplicaciones Web', 'DAW', 'Grado Superior', 2],
            ['Desarrollo de Apps Multiplataforma', 'DAM', 'Grado Superior', 2],
            ['Enfermería', 'ENF', 'Grado Medio', 1],
            ['Marketing', 'MK', 'Grado Superior', 2],
            ['Actividades Físicas y Deportivas', 'TAFAD', 'Grado Superior', 2]
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

        $manager->flush(); // Guardamos catálogos base

        // ======================================================
        // 6. USUARIOS Y PERFILES
        // ======================================================

        // --- 6.1 COORDINADOR (Maite) ---
        $u1 = new Usuario();
        $u1->setCorreo('maitesolam@gmail.com');
        $u1->setGoogleId('google_uid_maite');
        $u1->setRol($rolesCache['Coordinador']);
        $u1->setEstadoCuenta('Activa');
        $manager->persist($u1);

        $coord = new Coordinador();
        $coord->setUsuario($u1);
        $coord->setNombre('Maite');
        $coord->setApellidos('Sola');
        $coord->setTelefono('600111222');
        $manager->persist($coord);

        // --- 6.2 VOLUNTARIO 1: Pepe (Tecnológico) ---
        $u2 = new Usuario();
        $u2->setCorreo('pepe@test.com');
        $u2->setGoogleId('uid_pepe');
        $u2->setRol($rolesCache['Voluntario']);
        $u2->setEstadoCuenta('Activa');
        $manager->persist($u2);

        $v1 = new Voluntario();
        $v1->setUsuario($u2);
        $v1->setNombre('Pepe');
        $v1->setApellidos('Tecnológico');
        $v1->setDni('12345678A');
        $v1->setTelefono('600333444');
        $v1->setCursoActual($cursosCache['DAW']);
        $v1->setCarnetConducir(true);
        $v1->addPreferencia($tiposCache['Tecnológico / Digital']);
        $v1->addPreferencia($tiposCache['Educación']);
        $manager->persist($v1);

        $vi1 = new VoluntarioIdioma();
        $vi1->setVoluntario($v1);
        $vi1->setIdioma($idiomasCache['Inglés']);
        $vi1->setNivel('B2');
        $manager->persist($vi1);

        // --- 6.3 VOLUNTARIA 2: Laura (Salud / Medioambiente) ---
        $u3 = new Usuario();
        $u3->setCorreo('laura@test.com');
        $u3->setGoogleId('uid_laura');
        $u3->setRol($rolesCache['Voluntario']);
        $u3->setEstadoCuenta('Activa');
        $manager->persist($u3);

        $v2 = new Voluntario();
        $v2->setUsuario($u3);
        $v2->setNombre('Laura');
        $v2->setApellidos('Sanitaria');
        $v2->setDni('87654321B');
        $v2->setTelefono('600555666');
        $v2->setCursoActual($cursosCache['ENF']);
        $v2->addPreferencia($tiposCache['Salud / Sanitario']);
        $v2->addPreferencia($tiposCache['Medioambiente']);
        $manager->persist($v2);

        // --- 6.4 VOLUNTARIO 3: Carlos (Deportista / Animales) ---
        $u4 = new Usuario();
        $u4->setCorreo('carlos@test.com');
        $u4->setGoogleId('uid_carlos');
        $u4->setRol($rolesCache['Voluntario']);
        $u4->setEstadoCuenta('Activa');
        $manager->persist($u4);

        $v3 = new Voluntario();
        $v3->setUsuario($u4);
        $v3->setNombre('Carlos');
        $v3->setApellidos('Deportista');
        $v3->setDni('11223344C');
        $v3->setTelefono('600777888');
        $v3->setCursoActual($cursosCache['TAFAD']);
        $v3->addPreferencia($tiposCache['Deportivo']);
        $v3->addPreferencia($tiposCache['Protección Animal']);
        $manager->persist($v3);

        // --- 6.5 ONGs ---
        $uOrg1 = new Usuario();
        $uOrg1->setCorreo('info@techforgood.org');
        $uOrg1->setGoogleId('uid_org_tech');
        $uOrg1->setRol($rolesCache['Organizacion']);
        $uOrg1->setEstadoCuenta('Activa');
        $manager->persist($uOrg1);
        $org1 = new Organizacion();
        $org1->setUsuario($uOrg1);
        $org1->setNombre('Tech For Good');
        $org1->setCif('B11111111');
        $org1->setDescripcion('Tecnología social.');
        $manager->persist($org1);

        $uOrg2 = new Usuario();
        $uOrg2->setCorreo('contacto@ecovida.org');
        $uOrg2->setGoogleId('uid_org_eco');
        $uOrg2->setRol($rolesCache['Organizacion']);
        $uOrg2->setEstadoCuenta('Activa');
        $manager->persist($uOrg2);
        $org2 = new Organizacion();
        $org2->setUsuario($uOrg2);
        $org2->setNombre('EcoVida');
        $org2->setCif('G22222222');
        $org2->setDescripcion('Naturaleza viva.');
        $manager->persist($org2);

        $uOrg3 = new Usuario();
        $uOrg3->setCorreo('help@animalrescue.org');
        $uOrg3->setGoogleId('uid_org_animal');
        $uOrg3->setRol($rolesCache['Organizacion']);
        $uOrg3->setEstadoCuenta('Activa');
        $manager->persist($uOrg3);
        $org3 = new Organizacion();
        $org3->setUsuario($uOrg3);
        $org3->setNombre('Animal Rescue');
        $org3->setCif('G33333333');
        $org3->setDescripcion('Adopción y cuidado de animales.');
        $manager->persist($org3);

        // ======================================================
        // 7. ACTIVIDADES
        // ======================================================

        // Actividad 1: Taller Digital (Tech For Good)
        $act1 = new Actividad();
        $act1->setOrganizacion($org1);
        $act1->setTitulo('Taller de Alfabetización Digital');
        $act1->setDescripcion('Enseñar uso básico de móvil a mayores.');
        $act1->setUbicacion('Centro Cívico');
        $act1->setDuracionHoras(3);
        $act1->setCupoMaximo(5);
        $act1->setEstadoPublicacion('Publicada');
        $act1->setFechaInicio((new \DateTime())->modify('+5 days')->setTime(17, 0));
        if (isset($odsNombreMap['Educación de Calidad'])) $act1->addOd($odsNombreMap['Educación de Calidad']);
        $act1->addTiposVoluntariado($tiposCache['Tecnológico / Digital']);
        $manager->persist($act1);

        // Actividad 2: Limpieza Monte (EcoVida)
        $act2 = new Actividad();
        $act2->setOrganizacion($org2);
        $act2->setTitulo('Limpieza de Monte');
        $act2->setDescripcion('Recogida de residuos en el monte.');
        $act2->setUbicacion('Monte Ezcaba');
        $act2->setDuracionHoras(4);
        $act2->setCupoMaximo(20);
        $act2->setEstadoPublicacion('Publicada');
        $act2->setFechaInicio((new \DateTime())->modify('+2 days')->setTime(9, 0));
        if (isset($odsNombreMap['Vida de Ecosistemas Terrestres'])) $act2->addOd($odsNombreMap['Vida de Ecosistemas Terrestres']);
        $act2->addTiposVoluntariado($tiposCache['Medioambiente']);
        $manager->persist($act2);

        // Actividad 3: Paseo Perros (Animal Rescue)
        $act3 = new Actividad();
        $act3->setOrganizacion($org3);
        $act3->setTitulo('Paseo Solidario');
        $act3->setDescripcion('Pasear perros del refugio.');
        $act3->setUbicacion('Refugio Municipal');
        $act3->setDuracionHoras(2);
        $act3->setCupoMaximo(10);
        $act3->setEstadoPublicacion('Publicada');
        $act3->setFechaInicio((new \DateTime())->modify('+1 week')->setTime(10, 0));
        if (isset($odsNombreMap['Salud y Bienestar'])) $act3->addOd($odsNombreMap['Salud y Bienestar']);
        $act3->addTiposVoluntariado($tiposCache['Protección Animal']);
        $act3->addTiposVoluntariado($tiposCache['Deportivo']);
        $manager->persist($act3);

        // Actividad 4: Evento Pasado
        $act4 = new Actividad();
        $act4->setOrganizacion($org1);
        $act4->setTitulo('Charla Ciberseguridad');
        $act4->setDescripcion('Evento ya finalizado.');
        $act4->setUbicacion('Online');
        $act4->setDuracionHoras(1);
        $act4->setCupoMaximo(50);
        $act4->setEstadoPublicacion('Finalizada');
        $act4->setFechaInicio((new \DateTime())->modify('-1 week')->setTime(18, 0));
        $manager->persist($act4);

        // ======================================================
        // 8. INSCRIPCIONES
        // ======================================================

        // 1. Pepe se apunta al Taller Digital (PENDIENTE)
        $ins1 = new Inscripcion();
        $ins1->setVoluntario($v1);
        $ins1->setActividad($act1);
        $ins1->setEstadoSolicitud('Pendiente');
        $ins1->setFechaSolicitud(new \DateTime());
        $manager->persist($ins1);

        // 2. Laura se apunta a Limpieza (ACEPTADA)
        $ins2 = new Inscripcion();
        $ins2->setVoluntario($v2);
        $ins2->setActividad($act2);
        $ins2->setEstadoSolicitud('Aceptada');
        $ins2->setFechaSolicitud((new \DateTime())->modify('-1 day'));
        $manager->persist($ins2);

        // 3. Carlos se apunta al Taller Digital (RECHAZADA)
        $ins3 = new Inscripcion();
        $ins3->setVoluntario($v3);
        $ins3->setActividad($act1);
        $ins3->setEstadoSolicitud('Rechazada');
        $ins3->setFechaSolicitud((new \DateTime())->modify('-2 days'));
        $manager->persist($ins3);

        // 4. Carlos se apunta a Paseo Perros (PENDIENTE)
        $ins4 = new Inscripcion();
        $ins4->setVoluntario($v3);
        $ins4->setActividad($act3);
        $ins4->setEstadoSolicitud('Pendiente');
        $ins4->setFechaSolicitud(new \DateTime());
        $manager->persist($ins4);

        $manager->flush();
    }
}
