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
    // 1. LISTAR ORGANIZACIONES (GET) - VISTA SQL
    // ========================================================================
    #[Route('/organizaciones', name: 'listar_organizaciones', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Catálogo de ONGs activas',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'object'))
    )]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        // Usamos la Vista SQL para máximo rendimiento y consistencia con VoluntarioController
        $conn = $em->getConnection();
        $sql = 'SELECT * FROM VW_Organizaciones_Activas';

        try {
            $organizaciones = $conn->executeQuery($sql)->fetchAllAssociative();
            return $this->json($organizaciones, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al obtener organizaciones: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    // ========================================================================
    // 2. DETALLE ORGANIZACION (GET) - CON DTO
    // ========================================================================
    #[Route('/organizaciones/{id}', name: 'detalle_organizacion', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Response(
        response: 200,
        description: 'Detalle público de la Organización',
        content: new OA\JsonContent(
            ref: new Model(type: OrganizacionResponseDTO::class)
        )
    )]
    public function show(int $id, UsuarioRepository $userRepo, OrganizacionRepository $orgRepo): JsonResponse
    {
        // 1. Buscar Usuario base (para verificar soft delete)
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Organización no encontrada o inactiva'], Response::HTTP_NOT_FOUND);
        }

        // 2. Buscar Perfil Organización
        $organizacion = $orgRepo->findOneBy(['usuario' => $usuario]);
        if (!$organizacion) {
            return $this->json(['error' => 'Perfil de organización no configurado'], Response::HTTP_NOT_FOUND);
        }

        // 3. Devolver DTO
        return $this->json(OrganizacionResponseDTO::fromEntity($organizacion), Response::HTTP_OK);
    }

    // ========================================================================
    // 3. ACTUALIZAR PERFIL (PUT) - CON VALIDACIÓN DTO
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
        #[MapRequestPayload] OrganizacionUpdateDTO $dto, // Valida automáticamente
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

        // Actualizamos campos permitidos
        $organizacion->setNombre($dto->nombre);
        $organizacion->setDescripcion($dto->descripcion);
        $organizacion->setSitioWeb($dto->sitioWeb);
        $organizacion->setDireccion($dto->direccion);
        $organizacion->setTelefono($dto->telefono);

        try {
            $em->flush(); // El trigger actualizará updated_at

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
    // 4. CREAR ACTIVIDAD (POST) - Una organización puede añadir actividades
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
        // 1. Verificar que la organización existe
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Organización no encontrada'], Response::HTTP_NOT_FOUND);
        }

        $organizacion = $orgRepo->findOneBy(['usuario' => $usuario]);
        if (!$organizacion) {
            return $this->json(['error' => 'Perfil de organización no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // 2. Crear la actividad
        $actividad = new Actividad();
        $actividad->setOrganizacion($organizacion);
        $actividad->setTitulo($dto->titulo);
        $actividad->setDescripcion($dto->descripcion);
        $actividad->setUbicacion($dto->ubicacion);
        $actividad->setDuracionHoras($dto->duracion_horas);
        $actividad->setCupoMaximo($dto->cupo_maximo);
        $actividad->setEstadoPublicacion('En revision'); // Por defecto en revisión

        try {
            $actividad->setFechaInicio(new \DateTime($dto->fecha_inicio));
        } catch (\Exception $e) {
            return $this->json(['error' => 'Fecha inválida'], Response::HTTP_BAD_REQUEST);
        }

        // 3. Asignar ODS
        foreach ($dto->odsIds as $idOds) {
            $ods = $odsRepo->find($idOds);
            if ($ods) {
                $actividad->addOd($ods);
            }
        }

        // 4. Asignar Tipos de Voluntariado
        foreach ($dto->tiposIds as $idTipo) {
            $tipo = $tipoRepo->find($idTipo);
            if ($tipo) {
                $actividad->addTiposVoluntariado($tipo);
            }
        }

        // 5. Guardar
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
        ActividadRepository $actividadRepo
    ): JsonResponse {
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Organización no encontrada'], Response::HTTP_NOT_FOUND);
        }

        $organizacion = $orgRepo->findOneBy(['usuario' => $usuario]);
        if (!$organizacion) {
            return $this->json(['error' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // Obtener actividades de esta organización (solo las no eliminadas)
        $actividades = $actividadRepo->createQueryBuilder('a')
            ->where('a.organizacion = :org')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('org', $organizacion)
            ->orderBy('a.fechaInicio', 'DESC')
            ->getQuery()
            ->getResult();

        $respuesta = [];
        foreach ($actividades as $actividad) {
            $respuesta[] = ActividadResponseDTO::fromEntity($actividad);
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
            // Estadísticas de actividades
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

            // Estadísticas de inscripciones
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

            // Total de voluntarios únicos que han participado
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
        // 1. Verificar organización
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Organización no encontrada'], Response::HTTP_NOT_FOUND);
        }

        $organizacion = $orgRepo->findOneBy(['usuario' => $usuario]);
        if (!$organizacion) {
            return $this->json(['error' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // 2. Verificar que la actividad pertenece a esta organización
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

        // 3. Obtener voluntarios inscritos usando SQL directo para mejor rendimiento
        $conn = $em->getConnection();
        try {
            $voluntarios = $conn->executeQuery(
                'SELECT 
                    v.id_usuario as id_voluntario,
                    u.nombre,
                    u.apellidos,
                    u.email,
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
    // 8. CREAR ORGANIZACIÓN (POST) - Registro completo
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
    public function crear(Request $request, EntityManagerInterface $em, RolRepository $rolRepo): JsonResponse
    {
        // 1. Decodificar el JSON
        $data = json_decode($request->getContent(), true);

        // 🛡️ VALIDACIÓN DE SEGURIDAD (Para evitar errores 500 si faltan campos)
        // Usamos 'correo' para coincidir con la BBDD y el Test
        if (!isset($data['correo']) || !isset($data['nombre']) || !isset($data['cif'])) {
            return $this->json([
                'error' => 'Faltan datos obligatorios. Se requiere: correo, nombre, cif...'
            ], Response::HTTP_BAD_REQUEST);
        }

        // 2. Buscar el rol de Organización
        $rolOrganizacion = $rolRepo->findOneBy(['nombre' => 'Organizacion']);
        if (!$rolOrganizacion) {
            return $this->json(['error' => 'Rol de Organización no encontrado en la base de datos'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // 3. Crear primero el USUARIO (Padre)
        $usuario = new Usuario();
        $usuario->setCorreo($data['correo']); // Coherencia con BBDD
        $usuario->setGoogleId($data['google_id'] ?? 'generado_' . uniqid());
        $usuario->setRol($rolOrganizacion);
        $usuario->setEstadoCuenta('Pendiente'); // Valor por defecto seguro

        // ---------------------------------------------------------------------
        // 4. PRIMER GUARDADO (CRÍTICO: Generar ID de Usuario)
        // ---------------------------------------------------------------------
        $em->persist($usuario);
        $em->flush();

        // Ahora $usuario->getId() ya tiene un valor real gracias al flush anterior

        // 5. Crear la ORGANIZACION (Hija) vinculada a ese ID
        $organizacion = new Organizacion();
        $organizacion->setUsuario($usuario); // Doctrine usa el ID del usuario recién creado

        // Asignar datos específicos
        $organizacion->setNombre($data['nombre']);
        $organizacion->setCif($data['cif']);
        $organizacion->setTelefono($data['telefono'] ?? null); // Nullsafe
        $organizacion->setDireccion($data['direccion'] ?? null); // Nullsafe
        $organizacion->setDescripcion($data['descripcion'] ?? null);
        $organizacion->setSitioWeb($data['sitio_web'] ?? null);

        // 6. SEGUNDO GUARDADO (Persistir la Organización con el ID correcto)
        $em->persist($organizacion);
        $em->flush();

        // 7. Responder
        return $this->json([
            'message' => 'Organización creada con éxito',
            'id' => $usuario->getId()
        ], 201);
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
            // Query SQL optimizada: solo cuenta voluntarios con actividades REALIZADAS (Aceptada o Finalizada)
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

            // Añadir posición al ranking
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
}
