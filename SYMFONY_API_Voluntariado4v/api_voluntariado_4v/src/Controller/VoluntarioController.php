<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Entity\Voluntario;
use App\Entity\VoluntarioIdioma;
use App\Entity\Inscripcion;
use App\Entity\Actividad;
use App\Entity\Curso;
use App\Entity\TipoVoluntariado;
use App\Entity\Idioma;
use App\Entity\ImagenActividad;
// DTOs
use App\Model\Voluntario\VoluntarioCreateDTO;
use App\Model\Voluntario\VoluntarioResponseDTO;
use App\Model\Voluntario\VoluntarioUpdateDTO;
use App\Model\Inscripcion\InscripcionResponseDTO;
// Repositorios
use App\Repository\RolRepository;
use App\Repository\UsuarioRepository;
use App\Repository\ActividadRepository;
// Core
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
// Documentación
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Attribute\Model;

#[Route('', name: 'api_')]
#[OA\Tag(name: 'Voluntarios', description: 'Gestión de perfiles, inscripciones y estadísticas')]
final class VoluntarioController extends AbstractController
{
    // ========================================================================
    // HELPER: Seguridad Básica
    // ========================================================================
    private function checkOwner(Request $request, int $resourceId): bool
    {
        // Permitir si es Admin/Coordinador
        if ($request->headers->has('X-Admin-Id')) {
            return true;
        }

        $headerId = $request->headers->get('X-User-Id');
        // Si no hay header o no coincide, denegamos
        return $headerId && (int)$headerId === $resourceId;
    }

    // ========================================================================
    // 1. LISTADO (Vista SQL) - Público/Admin
    // ========================================================================
    #[Route('/voluntarios', name: 'listar_voluntarios', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Listado completo de voluntarios activos',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'object'))
    )]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $conn = $em->getConnection();
        $sql = 'SELECT * FROM VW_Voluntarios_Activos';
        try {
            $voluntarios = $conn->executeQuery($sql)->fetchAllAssociative();
            return $this->json($voluntarios, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al obtener datos'], 500);
        }
    }

    // ========================================================================
    // 2. REGISTRAR VOLUNTARIO (DTO)
    // ========================================================================
    #[Route('/voluntarios', name: 'registro_voluntario', methods: ['POST'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: VoluntarioCreateDTO::class)))]
    #[OA\Response(response: 201, description: 'Voluntario registrado', content: new OA\JsonContent(ref: new Model(type: VoluntarioResponseDTO::class)))]
    public function registrar(
        #[MapRequestPayload] VoluntarioCreateDTO $dto,
        EntityManagerInterface $em,
        RolRepository $rolRepository
    ): JsonResponse {

        try {
            // A. USUARIO BASE
            $usuario = new Usuario();
            $usuario->setCorreo($dto->correo);
            $usuario->setGoogleId($dto->google_id);
            $usuario->setEstadoCuenta('Pendiente');

            $rolVoluntario = $rolRepository->findOneBy(['nombre' => 'Voluntario']);
            if (!$rolVoluntario) throw new \Exception("Rol 'Voluntario' no encontrado");
            $usuario->setRol($rolVoluntario);

            $em->persist($usuario);
            $em->flush(); // Necesario para obtener el ID del usuario


            // B. PERFIL VOLUNTARIO
            $voluntario = new Voluntario();
            $voluntario->setUsuario($usuario);
            $voluntario->setNombre($dto->nombre);
            $voluntario->setApellidos($dto->apellidos);
            $voluntario->setDni($dto->dni);
            $voluntario->setTelefono($dto->telefono);
            $voluntario->setCarnetConducir($dto->carnet_conducir);
            $voluntario->setDescripcion($dto->descripcion);

            // Manejo seguro de fecha
            try {
                $voluntario->setFechaNac(new \DateTime($dto->fecha_nac));
            } catch (\Exception $e) {
                // El DTO ya valida el formato, esto es por seguridad extra
            }

            // IMPORTANTE: Asignar el curso ANTES del persist
            $curso = $em->getRepository(Curso::class)->find($dto->id_curso_actual);
            if ($curso) $voluntario->setCursoActual($curso);

            // PERSISTIR primero para generar el ID
            $em->persist($voluntario);

            // Ahora sí, añadir las relaciones Many-to-Many e Idiomas
            // Preferencias (Tipos)
            $tipoRepo = $em->getRepository(TipoVoluntariado::class);
            foreach ($dto->preferencias_ids as $tipoId) {
                $tipo = $tipoRepo->find($tipoId);
                if ($tipo) $voluntario->addPreferencia($tipo);
            }

            // Idiomas
            $idiomaRepo = $em->getRepository(Idioma::class);
            foreach ($dto->idiomas as $idiomaData) {
                $entidadIdioma = $idiomaRepo->find($idiomaData['id_idioma']);
                if ($entidadIdioma) {
                    $vi = new VoluntarioIdioma();
                    $vi->setVoluntario($voluntario);
                    $vi->setIdioma($entidadIdioma);
                    $vi->setNivel($idiomaData['nivel']);
                    $em->persist($vi);
                }
            }

            // FLUSH FINAL: Guarda voluntario + relaciones
            $em->flush();

            // Refrescamos para traer las relaciones cargadas
            $em->refresh($voluntario);

            return $this->json(VoluntarioResponseDTO::fromEntity($voluntario), Response::HTTP_CREATED);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE')) {
                return $this->json(['error' => 'El usuario (correo/DNI) ya existe'], 409);
            }

            // Otros errores
            return $this->json(['error' => 'Error al registrar el voluntario. Por favor, revisa los datos e inténtalo de nuevo.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ========================================================================
    // 3. GET ONE (Detalle)
    // ========================================================================
    #[Route('/voluntarios/{id}', name: 'get_voluntario', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Detalle del voluntario', content: new OA\JsonContent(ref: new Model(type: VoluntarioResponseDTO::class)))]
    public function getOne(int $id, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) return $this->json(['error' => 'No encontrado'], 404);

        $voluntario = $em->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]);
        if (!$voluntario) return $this->json(['error' => 'Perfil no encontrado'], 404);

        return $this->json(VoluntarioResponseDTO::fromEntity($voluntario), 200);
    }

    // ========================================================================
    // 4. ACTUALIZAR (PUT)
    // ========================================================================
    #[Route('/voluntarios/{id}', name: 'actualizar_voluntario', methods: ['PUT'])]
    #[OA\Parameter(name: 'X-User-Id', in: 'header', required: true, description: 'ID del usuario logueado', schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: VoluntarioUpdateDTO::class)))]
    #[OA\Response(response: 200, description: 'Perfil actualizado', content: new OA\JsonContent(ref: new Model(type: VoluntarioResponseDTO::class)))]
    public function actualizar(
        int $id,
        #[MapRequestPayload] VoluntarioUpdateDTO $dto,
        Request $request,
        UsuarioRepository $usuarioRepo,
        EntityManagerInterface $em
    ): JsonResponse {

        // 1. Seguridad: Solo el usuario propietario puede editar su perfil
        if (!$this->checkOwner($request, $id)) {
            return $this->json(['error' => 'No tienes permiso para editar este perfil'], Response::HTTP_FORBIDDEN);
        }

        // 2. Buscamos al Voluntario (Tuplas de BBDD)
        // Buscamos primero el Usuario padre para llegar al Voluntario
        $usuario = $usuarioRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], 404);

        $voluntario = $em->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]);
        if (!$voluntario) return $this->json(['error' => 'Perfil de voluntario no encontrado'], 404);

        // 3. Actualización de datos (SOLO TABLA VOLUNTARIO)
        $voluntario->setNombre($dto->nombre);
        $voluntario->setApellidos($dto->apellidos);
        $voluntario->setTelefono($dto->telefono);

        // Actualizar descripción si se proporciona
        if ($dto->descripcion !== null) {
            $voluntario->setDescripcion($dto->descripcion);
        }

        // Convertir el string fecha a DateTime
        if ($dto->fechaNac) {
            try {
                $voluntario->setFechaNac(new \DateTime($dto->fechaNac));
            } catch (\Exception $e) {
                // Si falla el formato, aunque el DTO valida, por seguridad no rompemos
            }
        }

        // Actualizar carnet de conducir si se proporciona
        if ($dto->carnet_conducir !== null) {
            $voluntario->setCarnetConducir($dto->carnet_conducir);
        }

        // Actualizar curso si se proporciona
        if ($dto->id_curso_actual) {
            $curso = $em->getRepository(Curso::class)->find($dto->id_curso_actual);
            if ($curso) {
                $voluntario->setCursoActual($curso);
            }
        }

        // NOTA: Aquí NO tocamos $usuario->setImgPerfil(). La foto es inmutable (Google).

        // 4. Sincronización de Preferencias (Many-to-Many)
        if ($dto->preferencias_ids !== null) { // Solo si envían el array (aunque sea vacío)
            // A. Limpiamos las actuales
            foreach ($voluntario->getPreferencias() as $pref) {
                $voluntario->removePreferencia($pref);
            }

            // B. Añadimos las nuevas
            if (!empty($dto->preferencias_ids)) {
                $tipoRepo = $em->getRepository(TipoVoluntariado::class);
                foreach ($dto->preferencias_ids as $idTipo) {
                    $tipo = $tipoRepo->find($idTipo);
                    if ($tipo) $voluntario->addPreferencia($tipo);
                }
            }
        }

        // 5. Persistir cambios
        $em->flush();

        return $this->json(VoluntarioResponseDTO::fromEntity($voluntario), 200);
    }

    // ========================================================================
    // 5. INSCRIBIRSE A ACTIVIDAD (POST)
    // ========================================================================
    #[Route('/voluntarios/{id}/actividades/{idActividad}', name: 'inscribirse_actividad', methods: ['POST'])]
    #[OA\Parameter(name: 'X-User-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 201, description: 'Inscripción realizada')]
    #[OA\Response(response: 409, description: 'Error de reglas de negocio (cupo, fechas, duplicado)')]
    public function inscribirse(
        int $id,
        int $idActividad,
        Request $request,
        UsuarioRepository $userRepo,
        ActividadRepository $actRepo,
        EntityManagerInterface $em
    ): JsonResponse {

        if (!$this->checkOwner($request, $id)) return $this->json(['error' => 'Acceso denegado'], 403);

        $usuario = $userRepo->find($id);
        $voluntario = ($usuario) ? $em->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]) : null;
        if (!$voluntario) return $this->json(['error' => 'Voluntario no encontrado'], 404);

        $actividad = $actRepo->find($idActividad);
        if (!$actividad) return $this->json(['error' => 'Actividad no encontrada'], 404);

        $inscripcion = new Inscripcion();
        $inscripcion->setVoluntario($voluntario);
        $inscripcion->setActividad($actividad);
        $inscripcion->setEstadoSolicitud('Pendiente');
        $inscripcion->setFechaSolicitud(new \DateTime());

        try {
            $em->persist($inscripcion);
            $em->flush(); // Aquí saltará el TRIGGER de Cupo/Fechas si no cumple
            return $this->json(['mensaje' => 'Inscripción solicitada correctamente'], 201);
        } catch (\Exception $e) {
            // Capturamos el error del Trigger SQL para dar un mensaje útil
            $msg = $e->getMessage();
            if (str_contains($msg, 'ERROR')) {
                // Intentamos limpiar el mensaje técnico de SQL Server
                // Ej: "SQLSTATE[...]: ... ERROR: La actividad ya está completa."
                $parts = explode('ERROR', $msg);
                $cleanMsg = isset($parts[1]) ? 'ERROR' . $parts[1] : 'No se pudo realizar la inscripción por reglas de negocio.';
                return $this->json(['error' => trim($cleanMsg)], 409);
            }
            return $this->json(['error' => 'No se pudo realizar la inscripción'], 500);
        }
    }

    // ========================================================================
    // 6. HISTORIAL DE INSCRIPCIONES Y ESTADÍSTICAS (GET)
    // ========================================================================
    #[Route('/voluntarios/{id}/historial', name: 'historial_voluntario', methods: ['GET'])]
    #[OA\Parameter(name: 'X-User-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Historial detallado y resumen',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'resumen', type: 'object'),
            new OA\Property(property: 'actividades', type: 'array', items: new OA\Items(ref: new Model(type: InscripcionResponseDTO::class)))
        ])
    )]
    public function historial(
        int $id,
        Request $request,
        UsuarioRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {

        if (!$this->checkOwner($request, $id)) return $this->json(['error' => 'Acceso denegado'], 403);

        $usuario = $userRepo->find($id);
        $voluntario = ($usuario) ? $em->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]) : null;
        if (!$voluntario) return $this->json(['error' => 'Voluntario no encontrado'], 404);

        $inscripciones = $em->getRepository(Inscripcion::class)->findBy(['voluntario' => $voluntario], ['fechaSolicitud' => 'DESC']);

        // A. Calcular Estadísticas
        $horasTotales = 0;
        $participacionesConfirmadas = 0;
        foreach ($inscripciones as $insc) {
            if (in_array($insc->getEstadoSolicitud(), ['Aceptada', 'Finalizada'])) {
                $participacionesConfirmadas++;
                $horasTotales += $insc->getActividad()->getDuracionHoras();
            }
        }

        // B. Mapear a DTO con inyeccion de imagen
        $imgRepo = $em->getRepository(ImagenActividad::class);
        $actividadesDTOs = [];

        foreach ($inscripciones as $ins) {
            $dto = InscripcionResponseDTO::fromEntity($ins);
            $img = $imgRepo->findOneBy(['actividad' => $ins->getActividad()->getId()]);
            if ($img) {
                $dto->imagen_actividad = $img->getUrlImagen();
            }
            $actividadesDTOs[] = $dto;
        }

        return $this->json([
            'resumen' => [
                'total_participaciones' => $participacionesConfirmadas,
                'horas_acumuladas' => $horasTotales,
                'nivel_experiencia' => $horasTotales > 50 ? 'Experto' : ($horasTotales > 20 ? 'Intermedio' : 'Principiante')
            ],
            'actividades' => $actividadesDTOs
        ], 200);
    }

    // ========================================================================
    // 7. DESAPUNTARSE (DELETE)
    // ========================================================================
    #[Route('/voluntarios/{id}/actividades/{idActividad}', name: 'desapuntarse_actividad', methods: ['DELETE'])]
    #[OA\Parameter(name: 'X-User-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function desapuntarse(
        int $id,
        int $idActividad,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {

        if (!$this->checkOwner($request, $id)) return $this->json(['error' => 'Acceso denegado'], 403);

        // Buscamos la inscripción (PK Compuesta: voluntario + actividad)
        // Usamos referencia para evitar query extra al obtener el voluntario
        $voluntarioRef = $em->getReference(Voluntario::class, $id);

        $inscripcion = $em->getRepository(Inscripcion::class)->findOneBy([
            'voluntario' => $voluntarioRef,
            'actividad' => $idActividad
        ]);

        if (!$inscripcion) {
            return $this->json(['error' => 'No estás inscrito en esta actividad'], 404);
        }

        // Opcional: Validar si se puede desapuntar (ej: si falta menos de 24h)
        // if ($inscripcion->getActividad()->getFechaInicio() < new \DateTime('+1 day')) ...

        $em->remove($inscripcion);
        $em->flush();

        return $this->json(['mensaje' => 'Te has desapuntado correctamente'], 200);
    }

    // ========================================================================
    // 8. RECOMENDACIONES (SP)
    // ========================================================================
    #[Route('/voluntarios/{id}/recomendaciones', name: 'recomendaciones_voluntario', methods: ['GET'])]
    #[OA\Parameter(name: 'X-User-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function recomendaciones(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkOwner($request, $id)) return $this->json(['error' => 'Acceso denegado'], 403);

        $conn = $em->getConnection();
        $sql = 'EXEC SP_Get_Recomendaciones_Voluntario @id_voluntario = :id';
        try {
            $actividades = $conn->executeQuery($sql, ['id' => $id])->fetchAllAssociative();
            return $this->json($actividades, 200);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al calcular recomendaciones'], 500);
        }
    }

    // ========================================================================
    // 9. HORAS TOTALES DE VOLUNTARIADO (GET) - Campo calculado
    // ========================================================================
    #[Route('/voluntarios/{id}/horas-totales', name: 'horas_totales_voluntario', methods: ['GET'])]
    #[OA\Parameter(name: 'X-User-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Total de horas de voluntariado realizadas (campo calculado)',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'horas_totales', type: 'integer', example: 45, description: 'Suma total de horas de actividades Aceptadas o Finalizadas')
            ]
        )
    )]
    public function horasTotales(int $id, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        // 1. Seguridad: Solo el propio voluntario puede ver sus horas
        if (!$this->checkOwner($request, $id)) {
            return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);
        }

        // 2. Buscar al voluntario
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $voluntario = $em->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]);
        if (!$voluntario) {
            return $this->json(['error' => 'Perfil de voluntario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // 3. Calcular horas totales usando solo SQL para mejor rendimiento
        $conn = $em->getConnection();

        $sql = "
            SELECT COALESCE(SUM(a.duracion_horas), 0) as horas_totales
            FROM INSCRIPCION i
            INNER JOIN ACTIVIDAD a ON i.id_actividad = a.id_actividad
            WHERE i.id_voluntario = :id_voluntario
            AND i.estado_solicitud IN ('Aceptada', 'Finalizada')
            AND a.deleted_at IS NULL
        ";

        try {
            $result = $conn->executeQuery($sql, ['id_voluntario' => $id])->fetchAssociative();
            $horasTotales = (int)$result['horas_totales'];

            return $this->json([
                'horas_totales' => $horasTotales
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al calcular horas totales'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
