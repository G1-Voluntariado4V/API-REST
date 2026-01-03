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
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    private ObjectManager $manager;
    private array $cache = [];

    public function load(ObjectManager $manager): void
    {
        $this->manager = $manager;

        echo ">>> 🚀 Iniciando carga de Fixtures (Modo Firebase/Google)...\n";

        // ======================================================
        // 1. CATÁLOGOS 
        // ======================================================

        // ROLES
        $rolesNombres = ['Administrador', 'Coordinador', 'Voluntario', 'Organizacion'];
        foreach ($rolesNombres as $nombre) {
            $this->createOrUpdateRol($nombre);
        }
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

        // ODS (Con IDs manuales)
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
        // 2. USUARIOS (IMAGEN EN USUARIO)
        // ======================================================

        // --- Coordinador ---
        $coordUser = $this->createOrUpdateUsuario('Coordinador', 'maitesolam@gmail.com', 'google_uid_maite', 'https://i.pravatar.cc/150?u=coord');

        // 🛠️ FIX 1: Hacemos flush AQUÍ para que $coordUser tenga ID real de BBDD
        $this->manager->flush();

        $this->createOrUpdatePerfilCoordinador($coordUser, 'Maite', 'Sola');

        // --- ONGs ---
        $ongs = [];
        $ongData = [
            ['Tech For Good', 'info@techforgood.org', 'uid_org_tech', 'Tecnología Social', 'https://ui-avatars.com/api/?name=Tech+Good&background=0D8ABC&color=fff'],
            ['EcoVida', 'contacto@ecovida.org', 'uid_org_eco', 'Medioambiente', 'https://ui-avatars.com/api/?name=Eco+Vida&background=27AE60&color=fff'],
            ['Animal Rescue', 'help@animalrescue.org', 'uid_org_animal', 'Refugio Animales', 'https://ui-avatars.com/api/?name=Animal+Rescue&background=E67E22&color=fff'],
            ['Cruz Roja Local', 'cruzroja@org.com', 'uid_cr', 'Ayuda Humanitaria', 'https://ui-avatars.com/api/?name=Cruz+Roja&background=C0392B&color=fff']
        ];
        foreach ($ongData as $d) {
            $u = $this->createOrUpdateUsuario('Organizacion', $d[1], $d[2], $d[4]);

            // 🛠️ FIX 2: Flush para obtener ID del Usuario antes de crear la Organización
            $this->manager->flush();

            $ongs[] = $this->createOrUpdatePerfilOrganizacion($u, $d[0], $d[3]);
        }

        // --- Voluntarios ---
        $vols = [];
        $volData = [
            ['Pepe', 'Pérez', 'pepe@test.com', 'uid_pepe', 'DAW', ['Tecnológico / Digital'], 'https://i.pravatar.cc/150?u=pepe'],
            ['Laura', 'Gómez', 'laura@test.com', 'uid_laura', 'ENF', ['Salud / Sanitario'], 'https://i.pravatar.cc/150?u=laura'],
            ['Carlos', 'Ruiz', 'carlos@test.com', 'uid_carlos', 'TAFAD', ['Deportivo', 'Protección Animal'], 'https://i.pravatar.cc/150?u=carlos'],
            ['Ana', 'López', 'ana@test.com', 'uid_ana', 'MK', ['Acción Social', 'Educación'], 'https://i.pravatar.cc/150?u=ana']
        ];
        foreach ($volData as $d) {
            $u = $this->createOrUpdateUsuario('Voluntario', $d[2], $d[3], $d[6]);

            // 🛠️ FIX 3: Flush para obtener ID del Usuario antes de crear el Voluntario
            $this->manager->flush();

            $v = $this->createOrUpdatePerfilVoluntario($u, $d[0], $d[1], $d[4]);

            // ... lógica de preferencias ...
            $vols[] = $v;
        }

        // 🛠️ FIX 4: Un último flush general para guardar los perfiles (Voluntarios/Orgs) y las actividades
        $manager->flush();

        // ======================================================
        // 3. ACTIVIDADES
        // ======================================================
        $acts = [];

        // Act 1
        $a1 = $this->createOrUpdateActividad($ongs[0], 'Taller de Alfabetización Digital', 'Publicada');
        $a1->setDescripcion('Clases de informática básica.');
        $a1->setFechaInicio((new \DateTime())->modify('+5 days')->setTime(17, 0));
        if (isset($this->cache['TipoVoluntariado']['Tecnológico / Digital'])) {
            $a1->addTiposVoluntariado($this->cache['TipoVoluntariado']['Tecnológico / Digital']);
        }
        // Asignamos el ODS 4 (Educación de Calidad)
        $odsEducacion = $this->manager->getRepository(ODS::class)->find(4);
        if ($odsEducacion) {
            $a1->addOd($odsEducacion); // Nota: el método suele ser addOd() o addOds() según tu Entity
        }
        $acts[] = $a1;

        // Act 2
        $a2 = $this->createOrUpdateActividad($ongs[1], 'Limpieza del Río Arga', 'Publicada');
        $a2->setDescripcion('Recogida de plásticos.');
        $a2->setFechaInicio((new \DateTime())->modify('+2 days')->setTime(9, 0));
        if (isset($this->cache['TipoVoluntariado']['Medioambiente'])) {
            $a2->addTiposVoluntariado($this->cache['TipoVoluntariado']['Medioambiente']);
        }
        // Asignamos el ODS 1 (Fin de la Pobreza)
        $odsPobreza = $this->manager->getRepository(ODS::class)->find(1);
        if ($odsPobreza) {
            $a2->addOd($odsPobreza); // Nota: el método suele ser addOd() o addOds() según tu Entity
        }
        $acts[] = $a2;

        // Act 3
        $a3 = $this->createOrUpdateActividad($ongs[2], 'Paseo Canino Solidario', 'Publicada');
        $a3->setDescripcion('Pasear perros del refugio.');
        $a3->setFechaInicio((new \DateTime())->modify('+1 week')->setTime(10, 0));
        if (isset($this->cache['TipoVoluntariado']['Protección Animal'])) {
            $a3->addTiposVoluntariado($this->cache['TipoVoluntariado']['Protección Animal']);
        }
        // Asignamos el ODS 1 (Fin de la Pobreza)
        $odsPobreza = $this->manager->getRepository(ODS::class)->find(1);
        if ($odsPobreza) {
            $a2->addOd($odsPobreza); // Nota: el método suele ser addOd() o addOds() según tu Entity
        }
        $acts[] = $a3;

        // Act 4
        $a4 = $this->createOrUpdateActividad($ongs[3], 'Gran Recogida de Alimentos', 'Finalizada');
        $a4->setDescripcion('Campaña de Navidad.');
        $a4->setFechaInicio((new \DateTime())->modify('-1 month')->setTime(9, 0));
        // Asignamos el ODS 1 (Fin de la Pobreza)
        $odsPobreza = $this->manager->getRepository(ODS::class)->find(1);
        if ($odsPobreza) {
            $a2->addOd($odsPobreza); // Nota: el método suele ser addOd() o addOds() según tu Entity
        }
        $acts[] = $a4;

        $manager->flush();

        // ======================================================
        // 4. INSCRIPCIONES
        // ======================================================

        $this->createOrUpdateInscripcion($vols[0], $a1, 'Aceptada');
        $this->createOrUpdateInscripcion($vols[3], $a1, 'Pendiente');
        $this->createOrUpdateInscripcion($vols[1], $a2, 'Aceptada');
        $this->createOrUpdateInscripcion($vols[2], $a2, 'Rechazada');
        $this->createOrUpdateInscripcion($vols[2], $a3, 'Aceptada');
        $this->createOrUpdateInscripcion($vols[0], $a3, 'Pendiente');



        $manager->flush();
        echo ">>> 🎉 ¡FIXTURES CARGADAS CON ÉXITO!\n";
    }

    // ======================================================
    // HELPER FUNCTIONS 
    // ======================================================

    private function createOrUpdateUsuario(string $rolName, string $email, string $googleId, ?string $img = null): Usuario
    {
        $repo = $this->manager->getRepository(Usuario::class);
        $usuario = $repo->findOneBy(['correo' => $email]);

        if (!$usuario) {
            $usuario = new Usuario();
            $usuario->setCorreo($email);
            $usuario->setGoogleId($googleId);
            $usuario->setFechaRegistro(new \DateTime());
        }

        $usuario->setDeletedAt(null);
        $usuario->setEstadoCuenta('Activa');
        $usuario->setImgPerfil($img); // 📸 FOTO AQUÍ

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
        if (!$org->getDireccion()) $org->setDireccion('Dirección desconocida');

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

    // --- Helpers Básicos ---

    private function createOrUpdateRol(string $nombre): void
    {
        $repo = $this->manager->getRepository(Rol::class);
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
            // ✅ CORREGIDO: Usamos constructor normal.
            // Al no tener @GeneratedValue, Doctrine insertará el ID que le pasamos aquí.
            $ods = new ODS($id, $nombre);
            $this->manager->persist($ods);
        }
    }

    private function createOrUpdateCurso(string $nom, string $abrev, string $grado, int $nivel): void
    {
        $repo = $this->manager->getRepository(Curso::class);
        $curso = $repo->findOneBy(['abreviacion' => $abrev]);
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
