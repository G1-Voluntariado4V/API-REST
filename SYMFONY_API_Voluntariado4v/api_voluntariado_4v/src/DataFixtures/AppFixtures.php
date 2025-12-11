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

class AppFixtures extends Fixture
{
    private ObjectManager $manager;
    private array $cache = []; // Para no hacer mil consultas repetidas

    public function load(ObjectManager $manager): void
    {
        $this->manager = $manager;

        // ======================================================
        // 1. CATÁLOGOS (Buscar si existe, si no crear)
        // ======================================================

        // ROLES
        $rolesNombres = ['Coordinador', 'Voluntario', 'Organizacion'];
        foreach ($rolesNombres as $nombre) {
            $this->createOrUpdateRol($nombre);
        }
        // Necesitamos flush aquí para asegurar que los roles tienen ID para los usuarios
        $manager->flush();

        // IDIOMAS
        $idiomasData = [['Español', 'ES'], ['Inglés', 'EN'], ['Francés', 'FR'], ['Alemán', 'DE'], ['Euskera', 'EU']];
        foreach ($idiomasData as $d) {
            $this->createOrUpdateIdioma($d[0], $d[1]);
        }

        // TIPOS DE VOLUNTARIADO
        $tiposData = ['Medioambiente', 'Acción Social', 'Educación', 'Protección Animal', 'Salud / Sanitario', 'Tecnológico / Digital', 'Deportivo', 'Cultural / Artístico', 'Emergencias'];
        foreach ($tiposData as $nombre) {
            $this->createOrUpdateTipo($nombre);
        }

        // ODS
        $odsData = [
            [1, 'Fin de la Pobreza'],
            [2, 'Hambre Cero'],
            [3, 'Salud y Bienestar'],
            [4, 'Educación de Calidad'],
            [5, 'Igualdad de Género'],
            [6, 'Agua Limpia y Saneamiento'],
            [7, 'Energía Asequible y No Contaminante'],
            [10, 'Reducción de las Desigualdades'],
            [11, 'Ciudades y Comunidades Sostenibles'],
            [12, 'Producción y Consumo Responsables'],
            [13, 'Acción por el Clima'],
            [14, 'Vida Submarina'],
            [15, 'Vida de Ecosistemas Terrestres'],
            [16, 'Paz, Justicia e Instituciones Sólidas']
        ];
        foreach ($odsData as $d) {
            $this->createOrUpdateODS($d[0], $d[1]);
        }

        // CURSOS
        $cursosData = [
            ['Desarrollo de Aplicaciones Web', 'DAW', 'Grado Superior', 2],
            ['Desarrollo de Apps Multiplataforma', 'DAM', 'Grado Superior', 2],
            ['Enfermería', 'ENF', 'Grado Medio', 1],
            ['Marketing', 'MK', 'Grado Superior', 2],
            ['Actividades Físicas y Deportivas', 'TAFAD', 'Grado Superior', 2]
        ];
        foreach ($cursosData as $d) {
            $this->createOrUpdateCurso($d[0], $d[1], $d[2], $d[3]);
        }

        $manager->flush();

        // ======================================================
        // 6. USUARIOS (REVIVIR Y ACTUALIZAR)
        // ======================================================

        // --- Coordinador ---
        // Buscamos por correo. Si existe (aunque esté borrado), lo actualizamos.
        $coordUser = $this->createOrUpdateUsuario('Coordinador', 'maitesolam@gmail.com', 'google_uid_maite');
        $this->createOrUpdatePerfilCoordinador($coordUser, 'Maite', 'Sola');

        // --- ONGs ---
        $ongs = [];
        $ongData = [
            ['Tech For Good', 'info@techforgood.org', 'uid_org_tech', 'Tecnología Social'],
            ['EcoVida', 'contacto@ecovida.org', 'uid_org_eco', 'Medioambiente'],
            ['Animal Rescue', 'help@animalrescue.org', 'uid_org_animal', 'Refugio Animales'],
            ['Cruz Roja Local', 'cruzroja@org.com', 'uid_cr', 'Ayuda Humanitaria']
        ];
        foreach ($ongData as $d) {
            $u = $this->createOrUpdateUsuario('Organizacion', $d[1], $d[2]);
            $ongs[] = $this->createOrUpdatePerfilOrganizacion($u, $d[0], $d[3]);
        }

        // --- Voluntarios ---
        $vols = [];
        $volData = [
            ['Pepe', 'Pérez', 'pepe@test.com', 'uid_pepe', 'DAW', ['Tecnológico / Digital']],
            ['Laura', 'Gómez', 'laura@test.com', 'uid_laura', 'ENF', ['Salud / Sanitario']],
            ['Carlos', 'Ruiz', 'carlos@test.com', 'uid_carlos', 'TAFAD', ['Deportivo', 'Protección Animal']],
            ['Ana', 'López', 'ana@test.com', 'uid_ana', 'MK', ['Acción Social', 'Educación']]
        ];
        foreach ($volData as $d) {
            $u = $this->createOrUpdateUsuario('Voluntario', $d[2], $d[3]);
            $v = $this->createOrUpdatePerfilVoluntario($u, $d[0], $d[1], $d[4]);

            // Preferencias (Solo añadimos si no las tiene ya)
            foreach ($d[5] as $prefName) {
                $tipo = $this->cache['TipoVoluntariado'][$prefName];
                if (!$v->getPreferencias()->contains($tipo)) {
                    $v->addPreferencia($tipo);
                }
            }
            $vols[] = $v;
        }
        $manager->flush();

        // ======================================================
        // 7. ACTIVIDADES (Buscar por Título o Crear)
        // ======================================================
        $acts = [];

        // Act 1
        $a1 = $this->createOrUpdateActividad($ongs[0], 'Taller de Alfabetización Digital', 'Publicada');
        $a1->setDescripcion('Clases de informática básica.');
        $a1->setFechaInicio((new \DateTime())->modify('+5 days')->setTime(17, 0));
        $a1->addTiposVoluntariado($this->cache['TipoVoluntariado']['Tecnológico / Digital']);
        $acts[] = $a1;

        // Act 2
        $a2 = $this->createOrUpdateActividad($ongs[1], 'Limpieza del Río Arga', 'Publicada');
        $a2->setDescripcion('Recogida de plásticos.');
        $a2->setFechaInicio((new \DateTime())->modify('+2 days')->setTime(9, 0));
        $a2->addTiposVoluntariado($this->cache['TipoVoluntariado']['Medioambiente']);
        $acts[] = $a2;

        // Act 3
        $a3 = $this->createOrUpdateActividad($ongs[2], 'Paseo Canino Solidario', 'Publicada');
        $a3->setDescripcion('Pasear perros del refugio.');
        $a3->setFechaInicio((new \DateTime())->modify('+1 week')->setTime(10, 0));
        $a3->addTiposVoluntariado($this->cache['TipoVoluntariado']['Protección Animal']);
        $acts[] = $a3;

        // Act 4 (Pasada)
        $a4 = $this->createOrUpdateActividad($ongs[3], 'Gran Recogida de Alimentos', 'Finalizada');
        $a4->setDescripcion('Campaña de Navidad.');
        $a4->setFechaInicio((new \DateTime())->modify('-1 month')->setTime(9, 0));
        $acts[] = $a4;

        $manager->flush();

        // ======================================================
        // 8. INSCRIPCIONES (Buscar si existe, sino crear)
        // ======================================================

        $this->createOrUpdateInscripcion($vols[0], $a1, 'Aceptada'); // Pepe -> Taller
        $this->createOrUpdateInscripcion($vols[3], $a1, 'Pendiente'); // Ana -> Taller

        $this->createOrUpdateInscripcion($vols[1], $a2, 'Aceptada'); // Laura -> Rio
        $this->createOrUpdateInscripcion($vols[2], $a2, 'Rechazada'); // Carlos -> Rio

        $this->createOrUpdateInscripcion($vols[2], $a3, 'Aceptada'); // Carlos -> Perros
        $this->createOrUpdateInscripcion($vols[0], $a3, 'Pendiente'); // Pepe -> Perros

        // Todos a la pasada
        foreach ($vols as $v) {
            $this->createOrUpdateInscripcion($v, $a4, 'Finalizada');
        }

        $manager->flush();
    }

    private function createOrUpdateUsuario(string $rolName, string $email, string $googleId): Usuario
    {
        $repo = $this->manager->getRepository(Usuario::class);
        $usuario = $repo->findOneBy(['correo' => $email]);

        if (!$usuario) {
            $usuario = new Usuario();
            $usuario->setCorreo($email);
            $usuario->setGoogleId($googleId);
        }

        $usuario->setDeletedAt(null);
        $usuario->setEstadoCuenta('Activa');

        // Buscamos el rol en la caché local que llenamos al principio
        if (isset($this->cache['Rol'][$rolName])) {
            $usuario->setRol($this->cache['Rol'][$rolName]);
        }

        $this->manager->persist($usuario);
        return $usuario;
    }

    private function createOrUpdatePerfilVoluntario(Usuario $u, string $nom, string $ape, string $cursoAbrev): Voluntario
    {
        $repo = $this->manager->getRepository(Voluntario::class);
        $vol = $repo->findOneBy(['usuario' => $u]);

        if (!$vol) {
            $vol = new Voluntario();
            $vol->setUsuario($u);
        }
        $vol->setNombre($nom);
        $vol->setApellidos($ape);
        if (!$vol->getDni()) $vol->setDni(rand(10000000, 99999999) . 'X');
        if (!$vol->getTelefono()) $vol->setTelefono('600' . rand(100000, 999999));

        if (isset($this->cache['Curso'][$cursoAbrev])) {
            $vol->setCursoActual($this->cache['Curso'][$cursoAbrev]);
        }

        $this->manager->persist($vol);
        return $vol;
    }

    private function createOrUpdatePerfilOrganizacion(Usuario $u, string $nom, string $desc): Organizacion
    {
        $repo = $this->manager->getRepository(Organizacion::class);
        $org = $repo->findOneBy(['usuario' => $u]);

        if (!$org) {
            $org = new Organizacion();
            $org->setUsuario($u);
            $org->setCif('G' . rand(10000000, 99999999));
        }
        $org->setNombre($nom);
        $org->setDescripcion($desc);
        $this->manager->persist($org);
        return $org;
    }

    private function createOrUpdatePerfilCoordinador(Usuario $u, string $nom, string $ape): Coordinador
    {
        $repo = $this->manager->getRepository(Coordinador::class);
        $coord = $repo->findOneBy(['usuario' => $u]);

        if (!$coord) {
            $coord = new Coordinador();
            $coord->setUsuario($u);
        }
        $coord->setNombre($nom);
        $coord->setApellidos($ape);
        $this->manager->persist($coord);
        return $coord;
    }

    private function createOrUpdateActividad(Organizacion $org, string $titulo, string $estado): Actividad
    {
        $repo = $this->manager->getRepository(Actividad::class);
        $act = $repo->findOneBy(['titulo' => $titulo, 'organizacion' => $org]);

        if (!$act) {
            $act = new Actividad();
            $act->setOrganizacion($org);
            $act->setTitulo($titulo);
            $act->setCupoMaximo(10);
            $act->setDuracionHoras(2);
        }
        $act->setDeletedAt(null);
        $act->setEstadoPublicacion($estado);

        $this->manager->persist($act);
        return $act;
    }

    private function createOrUpdateInscripcion(Voluntario $v, Actividad $a, string $estado): void
    {
        $repo = $this->manager->getRepository(Inscripcion::class);
        // Doctrine usa los objetos para la clave compuesta
        $ins = $repo->findOneBy(['voluntario' => $v, 'actividad' => $a]);

        if (!$ins) {
            $ins = new Inscripcion();
            $ins->setVoluntario($v);
            $ins->setActividad($a);
            $ins->setFechaSolicitud(new \DateTime());
        }
        $ins->setEstadoSolicitud($estado);
        $this->manager->persist($ins);
    }

    // --- Helpers Básicos (CORREGIDOS) ---

    private function createOrUpdateRol(string $nombre): void
    {
        $repo = $this->manager->getRepository(Rol::class);
        // CORRECCIÓN: Usamos 'nombre' (propiedad PHP) en vez de 'nombreRol'
        $rol = $repo->findOneBy(['nombre' => $nombre]);
        if (!$rol) {
            $rol = new Rol();
            $rol->setNombre($nombre);
            $this->manager->persist($rol);
        }
        $this->cache['Rol'][$nombre] = $rol;
    }

    private function createOrUpdateIdioma(string $nombre, string $iso): void
    {
        $repo = $this->manager->getRepository(Idioma::class);
        // CORRECCIÓN: Usamos 'codigoIso' (propiedad PHP)
        $idioma = $repo->findOneBy(['codigoIso' => $iso]);
        if (!$idioma) {
            $idioma = new Idioma();
            $idioma->setNombre($nombre);
            $idioma->setCodigoIso($iso);
            $this->manager->persist($idioma);
        }
        $this->cache['Idioma'][$nombre] = $idioma;
    }

    private function createOrUpdateTipo(string $nombre): void
    {
        $repo = $this->manager->getRepository(TipoVoluntariado::class);
        // CORRECCIÓN: Aquí es tricky. Si usaste 'make:entity', suele ser 'nombreTipo' o 'nombre'.
        // Si falla, cámbialo a 'nombre'.
        // Probamos con 'nombreTipo' que suele ser el defecto si la columna es nombre_tipo
        // Si te da error "Unrecognized field... nombreTipo", cambia esto a 'nombre'.
        $tipo = $repo->findOneBy(['nombreTipo' => $nombre]);
        if (!$tipo) {
            $tipo = new TipoVoluntariado();
            $tipo->setNombreTipo($nombre);
            $this->manager->persist($tipo);
        }
        $this->cache['TipoVoluntariado'][$nombre] = $tipo;
    }

    private function createOrUpdateODS(int $id, string $nombre): void
    {
        $repo = $this->manager->getRepository(ODS::class);
        $ods = $repo->find($id);
        if (!$ods) {
            $ods = new ODS($id, $nombre);
            $this->manager->persist($ods);
        } else {
            // Opcional: Actualizar nombre si ha cambiado
            // $ods->setNombre($nombre); 
        }
    }

    private function createOrUpdateCurso(string $nom, string $abrev, string $grado, int $nivel): void
    {
        $repo = $this->manager->getRepository(Curso::class);
        // CORRECCIÓN: Usamos 'abreviacionCurso' o 'abreviacion' según tu entidad.
        // Asumo 'abreviacion' basado en tu SQL. Si falla, mira tu Entity/Curso.php
        $curso = $repo->findOneBy(['abreviacion' => $abrev]); // Si falla pon 'abreviacionCurso'
        if (!$curso) {
            $curso = new Curso();
            $curso->setAbreviacion($abrev);
            $this->manager->persist($curso);
        }
        $curso->setNombre($nom);
        $curso->setGrado($grado);
        $curso->setNivel($nivel);
        $this->cache['Curso'][$abrev] = $curso;
    }
}
