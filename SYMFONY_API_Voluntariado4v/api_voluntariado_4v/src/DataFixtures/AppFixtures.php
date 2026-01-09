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
        $rolesNombres = ['Coordinador', 'Voluntario', 'Organizacion'];
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

        // ODS (Con IDs manuales y DESCRIPCIONES)
        $odsData = [
            [1, 'Fin de la Pobreza', 'Poner fin a la pobreza en todas sus formas en todo el mundo.'],
            [2, 'Hambre Cero', 'Poner fin al hambre, lograr la seguridad alimentaria y la mejora de la nutrición.'],
            [3, 'Salud y Bienestar', 'Garantizar una vida sana y promover el bienestar para todos en todas las edades.'],
            [4, 'Educación de Calidad', 'Garantizar una educación inclusiva, equitativa y de calidad.'],
            [5, 'Igualdad de Género', 'Lograr la igualdad entre los géneros y empoderar a todas las mujeres y niñas.'],
            [6, 'Agua Limpia y Saneamiento', 'Garantizar la disponibilidad de agua y su gestión sostenible.'],
            [7, 'Energía Asequible y No Contaminante', 'Garantizar el acceso a una energía asequible, segura, sostenible y moderna.'],
            [10, 'Reducción de las Desigualdades', 'Reducir la desigualdad en y entre los países.'],
            [11, 'Ciudades y Comunidades Sostenibles', 'Lograr que las ciudades sean más inclusivas, seguras, resilientes y sostenibles.'],
            [12, 'Producción y Consumo Responsables', 'Garantizar modalidades de consumo y producción sostenibles.'],
            [13, 'Acción por el Clima', 'Adoptar medidas urgentes para combatir el cambio climático y sus efectos.'],
            [14, 'Vida Submarina', 'Conservar y utilizar sosteniblemente los océanos, los mares y los recursos marinos.'],
            [15, 'Vida de Ecosistemas Terrestres', 'Gestionar sosteniblemente los bosques, luchar contra la desertificación y detener la pérdida de biodiversidad.'],
            [16, 'Paz, Justicia e Instituciones Sólidas', 'Promover sociedades justas, pacíficas e inclusivas.']
        ];
        foreach ($odsData as $d) {
            $this->createOrUpdateODS($d[0], $d[1], $d[2]);
        }

        // CURSOS (Basados en las imágenes proporcionadas)
        // Nivel 2 = Grado Superior, Nivel 1 = Grado Medio
        $cursosData = [
            // GRADO SUPERIOR (Nivel 2)
            ['Desarrollo de Aplicaciones Multiplataforma Dual', 'DAM', 'Grado Superior', 2],
            ['Administración de Sistemas Informáticos en Red Dual', 'ASIR', 'Grado Superior', 2],
            ['Transporte y Logística Dual', 'TL', 'Grado Superior', 2],
            ['Gestión de Ventas y Espacios Comerciales Dual', 'GVEC', 'Grado Superior', 2],
            ['Comercio Internacional Dual Bilingüe', 'CI', 'Grado Superior', 2],
            ['Administración y Finanzas Dual', 'ADFIN', 'Grado Superior', 2],

            // GRADO MEDIO (Nivel 1)
            ['Sistemas Microinformáticos y Redes', 'SMR', 'Grado Medio', 1],
            ['Actividades Comerciales Bilingüe', 'AC', 'Grado Medio', 1],
            ['Gestión Administrativa Bilingüe', 'GA', 'Grado Medio', 1]
        ];

        foreach ($cursosData as $d) {
            $this->createOrUpdateCurso($d[0], $d[1], $d[2], $d[3]);
        }

        $manager->flush();

        // ======================================================
        // 2. USUARIOS (SIN IMÁGENES)
        // ======================================================

        // --- Coordinador ---
        // Eliminado el argumento de imagen
        $coordUser = $this->createOrUpdateUsuario('Coordinador', 'maitesolam@gmail.com', 'google_uid_maite');

        // 🛠️ FIX 1: Hacemos flush AQUÍ para que $coordUser tenga ID real de BBDD
        $this->manager->flush();

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
            // Eliminado el argumento de imagen (índice 4 en tu array original)
            $u = $this->createOrUpdateUsuario('Organizacion', $d[1], $d[2]);

            // 🛠️ FIX 2: Flush para obtener ID del Usuario antes de crear la Organización
            $this->manager->flush();

            $ongs[] = $this->createOrUpdatePerfilOrganizacion($u, $d[0], $d[3]);
        }

        // --- Voluntarios ---
        $vols = [];
        // Actualizado para usar las abreviaciones nuevas (DAM, SMR, etc.)
        $volData = [
            ['Pepe', 'Pérez', 'pepe@test.com', 'uid_pepe', 'DAM', ['Tecnológico / Digital']],
            ['Laura', 'Gómez', 'laura@test.com', 'uid_laura', 'SMR', ['Salud / Sanitario']], // Puesto SMR por variar
            ['Carlos', 'Ruiz', 'carlos@test.com', 'uid_carlos', 'TL', ['Deportivo', 'Protección Animal']], // Puesto TL
            ['Ana', 'López', 'ana@test.com', 'uid_ana', 'GVEC', ['Acción Social', 'Educación']] // Puesto GVEC
        ];
        foreach ($volData as $d) {
            // Eliminado el argumento de imagen (índice 6 en tu array original)
            $u = $this->createOrUpdateUsuario('Voluntario', $d[2], $d[3]);

            // 🛠️ FIX 3: Flush para obtener ID del Usuario antes de crear el Voluntario
            $this->manager->flush();

            $v = $this->createOrUpdatePerfilVoluntario($u, $d[0], $d[1], $d[4]);

            // ... lógica de preferencias ...
            // Aquí deberías añadir la lógica para las preferencias usando $d[5] si la tienes implementada

            $vols[] = $v;
        }

        // 🛠️ FIX 4: Un último flush general para guardar los perfiles (Voluntarios/Orgs)
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
            $a1->addOd($odsEducacion);
        }
        $acts[] = $a1;

        // Act 2
        $a2 = $this->createOrUpdateActividad($ongs[1], 'Limpieza del Río Arga', 'Publicada');
        $a2->setDescripcion('Recogida de plásticos.');
        $a2->setFechaInicio((new \DateTime())->modify('+2 days')->setTime(9, 0));
        if (isset($this->cache['TipoVoluntariado']['Medioambiente'])) {
            $a2->addTiposVoluntariado($this->cache['TipoVoluntariado']['Medioambiente']);
        }
        // Asignamos el ODS 1 (Fin de la Pobreza) - (Ojo, quizás ODS 13 o 15 encaje mejor, pero mantengo tu lógica)
        $odsPobreza = $this->manager->getRepository(ODS::class)->find(1);
        if ($odsPobreza) {
            $a2->addOd($odsPobreza);
        }
        $acts[] = $a2;

        // Act 3
        $a3 = $this->createOrUpdateActividad($ongs[2], 'Paseo Canino Solidario', 'Publicada');
        $a3->setDescripcion('Pasear perros del refugio.');
        $a3->setFechaInicio((new \DateTime())->modify('+1 week')->setTime(10, 0));
        if (isset($this->cache['TipoVoluntariado']['Protección Animal'])) {
            $a3->addTiposVoluntariado($this->cache['TipoVoluntariado']['Protección Animal']);
        }
        // Asignamos el ODS 1
        if ($odsPobreza) {
            $a3->addOd($odsPobreza);
        }
        $acts[] = $a3;

        // Act 4
        $a4 = $this->createOrUpdateActividad($ongs[3], 'Gran Recogida de Alimentos', 'Finalizada');
        $a4->setDescripcion('Campaña de Navidad.');
        $a4->setFechaInicio((new \DateTime())->modify('-1 month')->setTime(9, 0));
        // Asignamos el ODS 1
        if ($odsPobreza) {
            $a4->addOd($odsPobreza);
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

    // Eliminado el parámetro $img
    private function createOrUpdateUsuario(string $rolName, string $email, string $googleId): Usuario
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

    // Actualizado para aceptar descripción
    private function createOrUpdateODS(int $id, string $nombre, string $descripcion): void
    {
        $repo = $this->manager->getRepository(ODS::class);
        $ods = $repo->find($id);

        if (!$ods) {
            // Asumiendo que tu constructor acepta ID y Nombre
            // Si no acepta ID, Doctrine se encarga, pero aquí parece que los IDs son fijos
            $ods = new ODS($id, $nombre);
            $ods->setDescripcion($descripcion); // ✅ Seteamos la descripción
            $this->manager->persist($ods);
        } else {
            // Si ya existe, actualizamos por si acaso cambias el texto
            $ods->setNombre($nombre);
            $ods->setDescripcion($descripcion);
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
