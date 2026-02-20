<?php

namespace App\Controller;


use App\Entity\Actividad;
use App\Entity\Inscripcion;
use App\Entity\Organizacion;
use App\Entity\Usuario;
use App\Model\Actividad\ActividadCreateDTO;
use App\Model\Actividad\ActividadResponseDTO;
use App\Model\Organizacion\OrganizacionCreateDTO;
use App\Model\Organizacion\OrganizacionResponseDTO;
use App\Model\Organizacion\OrganizacionUpdateDTO;
use App\Repository\ActividadRepository;
use App\Repository\ODSRepository;
use App\Repository\OrganizacionRepository;
use App\Repository\RolRepository;
use App\Repository\TipoVoluntariadoRepository;
use App\Repository\UsuarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Attribute\Model;

#[Route('', name: 'api_')]
#[OA\Tag(name: 'Organizaciones', description: 'Gestión de perfiles de ONGs')]
final class OrganizacionController extends AbstractController
{
    // ========================================================================
    // 1. LISTAR ORGANIZACIONES (GET)
    // ========================================================================
    #[Route('/organizaciones', name: 'listar_organizaciones', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Catálogo de ONGs activas',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id_usuario', type: 'integer', example: 5),
                    new OA\Property(property: 'correo_usuario', type: 'string', example: 'contacto@ong-ejemplo.org'),
                    new OA\Property(property: 'nombre_organizacion', type: 'string', example: 'ONG Ejemplo'),
                    new OA\Property(property: 'descripcion', type: 'string', example: 'Organización dedicada a...'),
                    new OA\Property(property: 'telefono', type: 'string', example: '+34 91 123 45 67'),
                    new OA\Property(property: 'direccion', type: 'string', example: 'Calle Solidaridad 12'),
                    new OA\Property(property: 'sitio_web', type: 'string', example: 'https://ong-ejemplo.org'),
                    new OA\Property(property: 'estado_cuenta', type: 'string', example: 'Activa')
                ],
                type: 'object'
            )
        )
    )]
    public function index(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $baseUrl = $request->getSchemeAndHttpHost();
        $conn = $em->getConnection();
        $sql = "
            SELECT
                u.id_usuario, u.correo as correo_usuario, u.estado_cuenta,
                o.nombre as nombre_organizacion, o.cif, o.telefono, o.sitio_web, o.direccion, o.descripcion,
                CASE
                    WHEN u.img_perfil IS NULL OR u.img_perfil = '' THEN NULL
                    WHEN u.img_perfil LIKE 'http%' THEN u.img_perfil
                    ELSE :base_url + '/uploads/usuarios/' + u.img_perfil
                END as img_perfil
            FROM USUARIO u
            JOIN ORGANIZACION o ON u.id_usuario = o.id_usuario
            WHERE u.estado_cuenta = 'Activa' AND u.deleted_at IS NULL
        ";

        try {
            $organizaciones = $conn->executeQuery($sql, ['base_url' => $baseUrl])->fetchAllAssociative();
            return $this->json($organizaciones, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al obtener organizaciones: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    // ========================================================================
    // 2. DETALLE ORGANIZACION (GET)
    // ========================================================================
    #[Route('/organizaciones/{id}', name: 'detalle_organizacion', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Response(
        response: 200,
        description: 'Detalle público de la Organización',
        content: new OA\JsonContent(
            ref: new Model(type: OrganizacionResponseDTO::class)
        )
    )]
    public function show(int $id, Request $request, UsuarioRepository $userRepo, OrganizacionRepository $orgRepo): JsonResponse
    {
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Organización no encontrada o inactiva'], Response::HTTP_NOT_FOUND);
        }

        $organizacion = $orgRepo->findOneBy(['usuario' => $usuario]);
        if (!$organizacion) {
            return $this->json(['error' => 'Perfil de organización no configurado'], Response::HTTP_NOT_FOUND);
        }

        $baseUrl = $request->getSchemeAndHttpHost();
        return $this->json(OrganizacionResponseDTO::fromEntity($organizacion, $baseUrl), Response::HTTP_OK);
    }

    // ========================================================================
    // 3. ACTUALIZAR PERFIL (PUT)
    // ========================================================================
    #[Route('/organizaciones/{id}', name: 'update_organizacion', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\RequestBody(
        description: 'Datos a actualizar',
        required: true,
        content: new OA\JsonContent(
            ref: new Model(type: OrganizacionUpdateDTO::class)
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Organización actualizada',
        content: new OA\JsonContent(
            ref: new Model(type: OrganizacionResponseDTO::class)
        )
    )]
    public function update(
        int $id,
        #[MapRequestPayload] OrganizacionUpdateDTO $dto,
        UsuarioRepository $userRepo,
        OrganizacionRepository $orgRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $organizacion = $orgRepo->findOneBy(['usuario' => $usuario]);
        if (!$organizacion) {
            return $this->json(['error' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $organizacion->setNombre($dto->nombre);
        $organizacion->setDescripcion($dto->descripcion);
        $organizacion->setSitioWeb($dto->sitioWeb);
        $organizacion->setDireccion($dto->direccion);
        $organizacion->setTelefono($dto->telefono);

        try {
            $em->flush();

            return $this->json(
                OrganizacionResponseDTO::fromEntity($organizacion),
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al actualizar: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    // ========================================================================
    // 4. CREAR ACTIVIDAD (POST)
    // ========================================================================
    #[Route('/organizaciones/{id}/actividades', name: 'crear_actividad_organizacion', methods: ['POST'])]
    #[OA\RequestBody(
        description: 'Datos para crear una nueva actividad',
        required: true,
        content: new OA\JsonContent(
            ref: new Model(type: ActividadCreateDTO::class)
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Actividad creada exitosamente',
        content: new OA\JsonContent(
            ref: new Model(type: ActividadResponseDTO::class)
        )
    )]
    public function crearActividad(
        int $id,
        #[MapRequestPayload] ActividadCreateDTO $dto,
        UsuarioRepository $userRepo,
        OrganizacionRepository $orgRepo,
        ODSRepository $odsRepo,
        TipoVoluntariadoRepository $tipoRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Organización no encontrada'], Response::HTTP_NOT_FOUND);
        }

        $organizacion = $orgRepo->findOneBy(['usuario' => $usuario]);
        if (!$organizacion) {
            return $this->json(['error' => 'Perfil de organización no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $actividad = new Actividad();
        $actividad->setOrganizacion($organizacion);
        $actividad->setTitulo($dto->titulo);
        $actividad->setDescripcion($dto->descripcion);
        $actividad->setUbicacion($dto->ubicacion);
        $actividad->setDuracionHoras($dto->duracion_horas);
        $actividad->setCupoMaximo($dto->cupo_maximo);
        $actividad->setEstadoPublicacion('En revision');

        try {
            $actividad->setFechaInicio(new \DateTime($dto->fecha_inicio));
        } catch (\Exception $e) {
            return $this->json(['error' => 'Fecha inválida'], Response::HTTP_BAD_REQUEST);
        }

        foreach ($dto->odsIds as $idOds) {
            $ods = $odsRepo->find($idOds);
            if ($ods) {
                $actividad->addOd($ods);
            }
        }

        foreach ($dto->tiposIds as $idTipo) {
            $tipo = $tipoRepo->find($idTipo);
            if ($tipo) {
                $actividad->addTiposVoluntariado($tipo);
            }
        }

        try {
            $em->persist($actividad);
            $em->flush();

            return $this->json(
                ActividadResponseDTO::fromEntity($actividad),
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al crear actividad: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    // ========================================================================
    // 4b. EDITAR ACTIVIDAD (PUT)
    // ========================================================================
    #[Route('/organizaciones/{id}/actividades/{actividadId}', name: 'editar_actividad_organizacion', methods: ['PUT'])]
    public function editarActividad(
        int $id,
        int $actividadId,
        Request $request,
        UsuarioRepository $userRepo,
        OrganizacionRepository $orgRepo,
        ActividadRepository $actividadRepo,
        ODSRepository $odsRepo,
        TipoVoluntariadoRepository $tipoRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Organización no encontrada'], Response::HTTP_NOT_FOUND);
        }

        $organizacion = $orgRepo->findOneBy(['usuario' => $usuario]);
        if (!$organizacion) {
            return $this->json(['error' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $actividad = $actividadRepo->find($actividadId);
        if (!$actividad || $actividad->getDeletedAt()) {
            return $this->json(['error' => 'Actividad no encontrada'], Response::HTTP_NOT_FOUND);
        }

        if ($actividad->getOrganizacion()->getId() !== $organizacion->getId()) {
            return $this->json(['error' => 'No tienes permiso para editar esta actividad'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['titulo'])) $actividad->setTitulo($data['titulo']);
        if (isset($data['descripcion'])) $actividad->setDescripcion($data['descripcion']);
        if (isset($data['ubicacion'])) $actividad->setUbicacion($data['ubicacion']);
        if (isset($data['duracion_horas'])) $actividad->setDuracionHoras((int)$data['duracion_horas']);
        if (isset($data['cupo_maximo'])) $actividad->setCupoMaximo((int)$data['cupo_maximo']);

        if (isset($data['fecha_inicio'])) {
            try {
                $actividad->setFechaInicio(new \DateTime($data['fecha_inicio']));
            } catch (\Exception $e) {}
        }

        // Si se edita, vuelve a revisión si ya estaba publicada (o se mantiene en revisión)
        $actividad->setEstadoPublicacion('En revision');

        // Relaciones ODS
        if (isset($data['odsIds']) && is_array($data['odsIds'])) {
            foreach ($actividad->getOds() as $ods) $actividad->removeOd($ods);
            foreach ($data['odsIds'] as $idOds) {
                $ods = $odsRepo->find($idOds);
                if ($ods) $actividad->addOd($ods);
            }
        }

        // Relaciones Tipos
        if (isset($data['tiposIds']) && is_array($data['tiposIds'])) {
            foreach ($actividad->getTiposVoluntariado() as $tipo) $actividad->removeTiposVoluntariado($tipo);
            foreach ($data['tiposIds'] as $idTipo) {
                $tipo = $tipoRepo->find($idTipo);
                if ($tipo) $actividad->addTiposVoluntariado($tipo);
            }
        }

        try {
            $em->flush();
            return $this->json(ActividadResponseDTO::fromEntity($actividad), Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // 4c. BORRAR ACTIVIDAD (DELETE)
    // ========================================================================
    #[Route('/organizaciones/{id}/actividades/{actividadId}', name: 'borrar_actividad_organizacion', methods: ['DELETE'])]
    public function borrarActividad(
        int $id,
        int $actividadId,
        UsuarioRepository $userRepo,
        OrganizacionRepository $orgRepo,
        ActividadRepository $actividadRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Organización no encontrada'], Response::HTTP_NOT_FOUND);
        }

        $organizacion = $orgRepo->findOneBy(['usuario' => $usuario]);
        if (!$organizacion) {
            return $this->json(['error' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $actividad = $actividadRepo->find($actividadId);
        if (!$actividad) return $this->json(['error' => 'Actividad no encontrada'], Response::HTTP_NOT_FOUND);

        if ($actividad->getOrganizacion()->getId() !== $organizacion->getId()) {
            return $this->json(['error' => 'No tienes permiso para borrar esta actividad'], Response::HTTP_FORBIDDEN);
        }

        try {
            // Usamos Soft Delete si existe el SP, o remove si no.
            // En CoordinadorController se usa el SP.
            $sql = 'EXEC SP_SoftDelete_Actividad @id_actividad = :id';
            $em->getConnection()->executeStatement($sql, ['id' => $actividadId]);

            return $this->json(['mensaje' => 'Actividad eliminada'], Response::HTTP_OK);
        } catch (\Exception $e) {
            // Fallback al remove de Doctrine si falla el SP (por si no está en este ambiente o no es MSSQL)
            try {
                $actividad->setDeletedAt(new \DateTime());
                $em->flush();
                return $this->json(['mensaje' => 'Actividad eliminada (soft delete via Doctrine)'], Response::HTTP_OK);
            } catch (\Exception $e2) {
                return $this->json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
            }
        }
    }

    // ========================================================================
    // 5. LISTAR ACTIVIDADES DE LA ORGANIZACIÓN (GET)
    // ========================================================================
    #[Route('/organizaciones/{id}/actividades', name: 'listar_actividades_organizacion', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Lista de actividades de la organización',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: ActividadResponseDTO::class))
        )
    )]
    public function listarActividades(
        int $id,
        UsuarioRepository $userRepo,
        OrganizacionRepository $orgRepo,
        ActividadRepository $actividadRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Organización no encontrada'], Response::HTTP_NOT_FOUND);
        }

        $organizacion = $orgRepo->findOneBy(['usuario' => $usuario]);
        if (!$organizacion) {
            return $this->json(['error' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $actividades = $actividadRepo->createQueryBuilder('a')
            ->where('a.organizacion = :org')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('org', $organizacion)
            ->orderBy('a.fechaInicio', 'DESC')
            ->getQuery()
            ->getResult();

        $respuesta = [];

        foreach ($actividades as $actividad) {
            $dto = ActividadResponseDTO::fromEntity($actividad);
            $dto->imagen_actividad = $actividad->getImgActividad();
            $dto->id_organizacion = $id;

            $respuesta[] = $dto;
        }

        return $this->json($respuesta, Response::HTTP_OK);
    }

    // ========================================================================
    // 6. VER ESTADÍSTICAS DE LA ORGANIZACIÓN (GET)
    // ========================================================================
    #[Route('/organizaciones/{id}/estadisticas', name: 'estadisticas_organizacion', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Estadísticas de la organización',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'total_actividades', type: 'integer', description: 'Total de actividades creadas'),
                new OA\Property(property: 'actividades_publicadas', type: 'integer', description: 'Actividades publicadas'),
                new OA\Property(property: 'actividades_en_revision', type: 'integer', description: 'Actividades en revisión'),
                new OA\Property(property: 'total_voluntarios', type: 'integer', description: 'Total de voluntarios únicos'),
                new OA\Property(property: 'total_inscripciones', type: 'integer', description: 'Total de inscripciones'),
                new OA\Property(property: 'inscripciones_confirmadas', type: 'integer', description: 'Inscripciones confirmadas'),
                new OA\Property(property: 'inscripciones_pendientes', type: 'integer', description: 'Inscripciones pendientes')
            ]
        )
    )]
    public function estadisticas(
        int $id,
        UsuarioRepository $userRepo,
        OrganizacionRepository $orgRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Organización no encontrada'], Response::HTTP_NOT_FOUND);
        }

        $organizacion = $orgRepo->findOneBy(['usuario' => $usuario]);
        if (!$organizacion) {
            return $this->json(['error' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $conn = $em->getConnection();

        try {
            $totalActividades = $conn->executeQuery(
                'SELECT COUNT(*) as total FROM ACTIVIDAD WHERE id_organizacion = :org AND deleted_at IS NULL',
                ['org' => $id]
            )->fetchAssociative()['total'];

            $actividadesPublicadas = $conn->executeQuery(
                'SELECT COUNT(*) as total FROM ACTIVIDAD WHERE id_organizacion = :org AND estado_publicacion = :estado AND deleted_at IS NULL',
                ['org' => $id, 'estado' => 'Publicada']
            )->fetchAssociative()['total'];

            $actividadesEnRevision = $conn->executeQuery(
                'SELECT COUNT(*) as total FROM ACTIVIDAD WHERE id_organizacion = :org AND estado_publicacion = :estado AND deleted_at IS NULL',
                ['org' => $id, 'estado' => 'En revision']
            )->fetchAssociative()['total'];

            $totalInscripciones = $conn->executeQuery(
                'SELECT COUNT(*) as total FROM INSCRIPCION i
                 INNER JOIN ACTIVIDAD a ON i.id_actividad = a.id_actividad
                 WHERE a.id_organizacion = :org AND a.deleted_at IS NULL',
                ['org' => $id]
            )->fetchAssociative()['total'];

            $inscripcionesConfirmadas = $conn->executeQuery(
                'SELECT COUNT(*) as total FROM INSCRIPCION i
                 INNER JOIN ACTIVIDAD a ON i.id_actividad = a.id_actividad
                 WHERE a.id_organizacion = :org AND i.estado_solicitud = :estado AND a.deleted_at IS NULL',
                ['org' => $id, 'estado' => 'Confirmada']
            )->fetchAssociative()['total'];

            $inscripcionesPendientes = $conn->executeQuery(
                'SELECT COUNT(*) as total FROM INSCRIPCION i
                 INNER JOIN ACTIVIDAD a ON i.id_actividad = a.id_actividad
                 WHERE a.id_organizacion = :org AND i.estado_solicitud = :estado AND a.deleted_at IS NULL',
                ['org' => $id, 'estado' => 'Pendiente']
            )->fetchAssociative()['total'];

            $totalVoluntarios = $conn->executeQuery(
                'SELECT COUNT(DISTINCT i.id_voluntario) as total FROM INSCRIPCION i
                 INNER JOIN ACTIVIDAD a ON i.id_actividad = a.id_actividad
                 WHERE a.id_organizacion = :org AND a.deleted_at IS NULL',
                ['org' => $id]
            )->fetchAssociative()['total'];

            return $this->json([
                'total_actividades' => (int)$totalActividades,
                'actividades_publicadas' => (int)$actividadesPublicadas,
                'actividades_en_revision' => (int)$actividadesEnRevision,
                'total_voluntarios' => (int)$totalVoluntarios,
                'total_inscripciones' => (int)$totalInscripciones,
                'inscripciones_confirmadas' => (int)$inscripcionesConfirmadas,
                'inscripciones_pendientes' => (int)$inscripcionesPendientes
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al obtener estadísticas: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    // ========================================================================
    // 7. VER VOLUNTARIOS DE UNA ACTIVIDAD (GET)
    // ========================================================================
    #[Route('/organizaciones/{id}/actividades/{actividadId}/voluntarios', name: 'voluntarios_actividad_organizacion', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Lista de voluntarios inscritos en la actividad',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id_voluntario', type: 'integer'),
                    new OA\Property(property: 'nombre', type: 'string'),
                    new OA\Property(property: 'apellidos', type: 'string'),
                    new OA\Property(property: 'email', type: 'string'),
                    new OA\Property(property: 'fecha_solicitud', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'estado_solicitud', type: 'string', enum: ['Pendiente', 'Confirmada', 'Rechazada'])
                ]
            )
        )
    )]
    public function voluntariosActividad(
        int $id,
        int $actividadId,
        UsuarioRepository $userRepo,
        OrganizacionRepository $orgRepo,
        ActividadRepository $actividadRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Organización no encontrada'], Response::HTTP_NOT_FOUND);
        }

        $organizacion = $orgRepo->findOneBy(['usuario' => $usuario]);
        if (!$organizacion) {
            return $this->json(['error' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $actividad = $actividadRepo->find($actividadId);
        if (!$actividad || $actividad->getDeletedAt()) {
            return $this->json(['error' => 'Actividad no encontrada'], Response::HTTP_NOT_FOUND);
        }

        if ($actividad->getOrganizacion()->getId() !== $organizacion->getId()) {
            return $this->json(
                ['error' => 'Esta actividad no pertenece a su organización'],
                Response::HTTP_FORBIDDEN
            );
        }

        $conn = $em->getConnection();
        try {
            $voluntarios = $conn->executeQuery(
                'SELECT
                    v.id_usuario as id_voluntario,
                    v.nombre,
                    v.apellidos,
                    v.dni,
                    v.telefono,
                    v.descripcion as bio,
                    v.fecha_nac,
                    u.correo as email,
                    i.fecha_solicitud,
                    i.estado_solicitud
                 FROM INSCRIPCION i
                 INNER JOIN VOLUNTARIO v ON i.id_voluntario = v.id_usuario
                 INNER JOIN USUARIO u ON v.id_usuario = u.id_usuario
                 WHERE i.id_actividad = :actividad
                 AND u.deleted_at IS NULL
                 ORDER BY i.fecha_solicitud DESC',
                ['actividad' => $actividadId]
            )->fetchAllAssociative();

            return $this->json($voluntarios, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al obtener voluntarios: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    // ========================================================================
    // 8. CREAR ORGANIZACIÓN (POST)
    // ========================================================================
    #[Route('/organizaciones', name: 'api_crear_organizacion', methods: ['POST'])]
    #[OA\RequestBody(
        description: "Datos para registrar una nueva organización y su usuario",
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "correo", type: "string", example: "contacto@ong.org"),
                new OA\Property(property: "nombre", type: "string", example: "ONG Ejemplo"),
                new OA\Property(property: "cif", type: "string", example: "G12345678"),
                new OA\Property(property: "telefono", type: "string", example: "600123456"),
                new OA\Property(property: "direccion", type: "string", example: "Calle Falsa 123"),
                new OA\Property(property: "descripcion", type: "string", example: "Descripción de la ONG"),
                new OA\Property(property: "sitio_web", type: "string", example: "https://www.ong.org"),
                new OA\Property(property: "google_id", type: "string", example: "token_google_123")
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Organización creada correctamente',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "message", type: "string"),
                new OA\Property(property: "id", type: "integer")
            ]
        )
    )]
    public function crear(
        Request $request,
        EntityManagerInterface $em,
        RolRepository $rolRepo,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%uploads_directory%')] string $uploadsDirectory
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['correo']) || !isset($data['nombre']) || !isset($data['cif'])) {
            return $this->json([
                'error' => 'Faltan datos obligatorios. Se requiere: correo, nombre, cif...'
            ], Response::HTTP_BAD_REQUEST);
        }

        $rolOrganizacion = $rolRepo->findOneBy(['nombre' => 'Organizacion']);
        if (!$rolOrganizacion) {
            return $this->json(['error' => 'Rol de Organización no encontrado en la base de datos'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $em->beginTransaction();
        try {
            $usuario = new Usuario();
            $usuario->setCorreo($data['correo']);
            $usuario->setGoogleId($data['google_id'] ?? 'generado_' . uniqid());
            $usuario->setRol($rolOrganizacion);
            $usuario->setEstadoCuenta('Pendiente');

            $em->persist($usuario);
            $em->flush(); // Necesario para obtener el ID para el nombre de la imagen

            if (isset($data['img_perfil']) && str_starts_with($data['img_perfil'], 'http')) {
                $filename = $this->saveGoogleImage($data['img_perfil'], $usuario->getId(), $uploadsDirectory);
                if ($filename) {
                    $usuario->setImgPerfil($filename);
                }
            } elseif (isset($data['img_perfil'])) {
                $usuario->setImgPerfil($data['img_perfil']);
            }

            $em->persist($usuario);
            // ELIMINADO: $em->flush() intermedio

            $organizacion = new Organizacion();
            $organizacion->setUsuario($usuario);

            $organizacion->setNombre($data['nombre']);
            $organizacion->setCif($data['cif']);
            $organizacion->setTelefono($data['telefono'] ?? null);
            $organizacion->setDireccion($data['direccion'] ?? null);
            $organizacion->setDescripcion($data['descripcion'] ?? null);
            $organizacion->setSitioWeb($data['sitio_web'] ?? null);

            $em->persist($organizacion);
            $em->flush();

            $em->commit();

            return $this->json([
                'message' => 'Organización creada con éxito',
                'id' => $usuario->getId()
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            $em->rollback();

            $errorMsg = $e->getMessage();

            if (
                str_contains($errorMsg, 'duplicada') ||
                str_contains($errorMsg, 'Duplicate') ||
                str_contains($errorMsg, 'UNIQUE') ||
                str_contains($errorMsg, '2601')
            ) {

                if (str_contains($errorMsg, 'UNIQ_1D204E4777040BC9') || str_contains($errorMsg, 'correo')) {
                    return $this->json([
                        'error' => 'Ese correo electrónico ya está registrado en nuestra base de datos'
                    ], Response::HTTP_CONFLICT);
                } elseif (str_contains($errorMsg, 'UNIQ_9912454AA53EB8E8') || str_contains($errorMsg, 'cif')) {
                    return $this->json([
                        'error' => 'Ese CIF ya está registrado en nuestra base de datos'
                    ], Response::HTTP_CONFLICT);
                } else {
                    return $this->json([
                        'error' => 'Esa organización ya está registrada en nuestra base de datos'
                    ], Response::HTTP_CONFLICT);
                }
            }

            return $this->json([
                'error' => 'Error al registrar la organización. Por favor, revisa los datos e inténtalo de nuevo.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ========================================================================
    // 9. TOP 3 ORGANIZACIONES POR NÚMERO DE VOLUNTARIOS REALIZADOS (GET)
    // ========================================================================
    #[Route('/organizaciones/top-voluntarios', name: 'top_organizaciones_voluntarios', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Top 3 de organizaciones con más voluntarios que han realizado actividades',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'posicion', type: 'integer', example: 1),
                    new OA\Property(property: 'id_organizacion', type: 'integer', example: 5),
                    new OA\Property(property: 'nombre', type: 'string', example: 'Cruz Roja'),
                    new OA\Property(property: 'cif', type: 'string', example: 'G12345678'),
                    new OA\Property(property: 'total_voluntarios', type: 'integer', example: 156, description: 'Voluntarios únicos con actividades Aceptadas o Finalizadas'),
                    new OA\Property(property: 'total_actividades', type: 'integer', example: 45),
                    new OA\Property(property: 'descripcion', type: 'string', example: 'Ayuda humanitaria')
                ]
            )
        )
    )]
    public function topVoluntarios(EntityManagerInterface $em): JsonResponse
    {
        $conn = $em->getConnection();

        try {
            $sql = "
                SELECT TOP 3
                    o.id_usuario as id_organizacion,
                    o.nombre,
                    o.cif,
                    o.descripcion,
                    o.telefono,
                    o.sitio_web,
                    COUNT(DISTINCT i.id_voluntario) as total_voluntarios,
                    COUNT(DISTINCT a.id_actividad) as total_actividades
                FROM ORGANIZACION o
                INNER JOIN USUARIO u ON o.id_usuario = u.id_usuario
                INNER JOIN ACTIVIDAD a ON o.id_usuario = a.id_organizacion
                INNER JOIN INSCRIPCION i ON a.id_actividad = i.id_actividad
                WHERE u.deleted_at IS NULL
                    AND u.estado_cuenta = 'Activa'
                    AND a.deleted_at IS NULL
                    AND i.estado_solicitud IN ('Aceptada', 'Finalizada')
                GROUP BY
                    o.id_usuario,
                    o.nombre,
                    o.cif,
                    o.descripcion,
                    o.telefono,
                    o.sitio_web
                HAVING COUNT(DISTINCT i.id_voluntario) > 0
                ORDER BY total_voluntarios DESC, total_actividades DESC
            ";

            $resultados = $conn->executeQuery($sql)->fetchAllAssociative();

            $respuesta = [];
            $posicion = 1;
            foreach ($resultados as $org) {
                $respuesta[] = [
                    'posicion' => $posicion++,
                    'id_organizacion' => (int)$org['id_organizacion'],
                    'nombre' => $org['nombre'],
                    'cif' => $org['cif'],
                    'total_voluntarios' => (int)$org['total_voluntarios'],
                    'total_actividades' => (int)$org['total_actividades'],
                    'descripcion' => $org['descripcion'],
                    'telefono' => $org['telefono'],
                    'sitio_web' => $org['sitio_web']
                ];
            }

            return $this->json($respuesta, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al obtener el ranking: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function saveGoogleImage(string $url, int $userId, string $uploadsDirectory): ?string
    {
        try {
            $opts = [
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: PHP\r\n"
                ]
            ];
            $context = stream_context_create($opts);
            $content = @file_get_contents($url, false, $context);
            if ($content === false) return null;

            $filename = 'google_' . $userId . '_' . uniqid() . '.jpg';
            $targetDir = $uploadsDirectory . '/usuarios';
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            file_put_contents($targetDir . '/' . $filename, $content);
            return $filename;
        } catch (\Exception $e) {
            return null;
        }
    }
}
