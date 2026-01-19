<?php

namespace App\Controller;

use App\Entity\Actividad;
use App\Entity\ImagenActividad;
use App\Entity\Organizacion;
// Modelos / DTOs
use App\Model\Actividad\ActividadCreateDTO;
use App\Model\Actividad\ActividadUpdateDTO; // <--- El nuevo que acabamos de crear
use App\Model\Actividad\ActividadResponseDTO;
// Repositorios
use App\Repository\ActividadRepository;
use App\Repository\ODSRepository;
use App\Repository\TipoVoluntariadoRepository;
use App\Repository\UsuarioRepository;
// Core Symfony & Doctrine
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
#[OA\Tag(name: 'Actividades', description: 'Gestión de ofertas de voluntariado')]
final class ActividadController extends AbstractController
{
    // ========================================================================
    // 1. LISTAR ACTIVIDADES (GET) - VISTA SQL
    // ========================================================================
    #[Route('/actividades', name: 'listar_actividades', methods: ['GET'])]
    #[OA\Parameter(name: 'ods_id', description: 'Filtrar por ID de ODS', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'tipo_id', description: 'Filtrar por ID de Tipo Voluntariado', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Catálogo de actividades publicadas (Vista SQL optimizada)',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'object'))
    )]
    public function index(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $odsId = $request->query->get('ods_id');
        $tipoId = $request->query->get('tipo_id');

        $conn = $em->getConnection();
        $qb = $conn->createQueryBuilder();

        // Usamos la VISTA SQL por rendimiento (devuelve array asociativo, no Entidades)
        $qb->select('*')->from('VW_Actividades_Publicadas');

        if ($odsId) {
            $qb->andWhere('id_actividad IN (SELECT id_actividad FROM ACTIVIDAD_ODS WHERE id_ods = :ods)')
                ->setParameter('ods', $odsId);
        }

        if ($tipoId) {
            $qb->andWhere('id_actividad IN (SELECT id_actividad FROM ACTIVIDAD_TIPO WHERE id_tipo = :tipo)')
                ->setParameter('tipo', $tipoId);
        }

        try {
            $actividades = $qb->executeQuery()->fetchAllAssociative();

            foreach ($actividades as &$actividad) {
                $id = $actividad['id_actividad'];
                
                // 1. TIPOS DE VOLUNTARIADO (Desde Entidad Actividad)
                $entity = $em->getRepository(Actividad::class)->find($id);
                if ($entity) {
                    $nombresTipos = [];
                    foreach ($entity->getTiposVoluntariado() as $tipoEntity) {
                        if ($tipoEntity->getNombreTipo()) {
                             $nombresTipos[] = $tipoEntity->getNombreTipo();
                        }
                    }
                    if (count($nombresTipos) > 0) {
                        $actividad['tipo'] = implode(', ', $nombresTipos);
                    }
                    
                    // ASEGURAR ID ORGANIZACIÓN (Crucial para navegación)
                    if ($entity->getOrganizacion()) {
                        $actividad['id_organizacion'] = $entity->getOrganizacion()->getId();
                        $actividad['nombre_organizacion'] = $entity->getOrganizacion()->getNombre() ?? $actividad['nombre_organizacion'];
                    }
                }

                // 2. IMAGEN (Desde ImagenActividadRepository)
                $imgRepo = $em->getRepository(ImagenActividad::class);
                // findOneBy devuelve la primera que encuentre 
                $imagenEntity = $imgRepo->findOneBy(['actividad' => $id]);
                
                if ($imagenEntity) {
                    $actividad['imagen_actividad'] = $imagenEntity->getUrlImagen();
                } else {
                    $actividad['imagen_actividad'] = null;
                }
            }
            unset($actividad);

            return $this->json($actividades, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al cargar catálogo: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    // ========================================================================
    // 2. CREAR ACTIVIDAD (POST) - Usando ActividadCreateDTO
    // ========================================================================
    #[Route('/actividades', name: 'crear_actividad', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: new Model(type: ActividadCreateDTO::class)
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Actividad creada correctamente',
        content: new OA\JsonContent(
            ref: new Model(type: ActividadResponseDTO::class)
        )
    )]
    public function crear(
        #[MapRequestPayload] ActividadCreateDTO $dto, // <--- Validación automática
        EntityManagerInterface $entityManager,
        UsuarioRepository $userRepo,
        ODSRepository $odsRepo,
        TipoVoluntariadoRepository $tipoRepo
    ): JsonResponse {

        // 1. Buscar la Organización dueña (Necesario porque viene en el DTO de creación)
        // Buscamos el Usuario primero
        $usuarioOrg = $userRepo->find($dto->id_organizacion);
        if (!$usuarioOrg) {
            return $this->json(['error' => 'Organización no encontrada'], Response::HTTP_NOT_FOUND);
        }

        // Buscamos el perfil de Organización asociado
        $organizacion = $entityManager->getRepository(Organizacion::class)->findOneBy(['usuario' => $usuarioOrg]);
        if (!$organizacion) {
            return $this->json(['error' => 'Perfil de organización no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // 2. Crear la Entidad y setear datos básicos
        $actividad = new Actividad();
        $actividad->setOrganizacion($organizacion);
        $actividad->setTitulo($dto->titulo);
        $actividad->setDescripcion($dto->descripcion);
        $actividad->setUbicacion($dto->ubicacion);
        $actividad->setDuracionHoras($dto->duracion_horas);
        $actividad->setCupoMaximo($dto->cupo_maximo);
        $actividad->setEstadoPublicacion('En revision');

        // El DTO garantiza formato fecha, pero DateTime puede fallar si la fecha es lógica pero rara (ej: mes 13)
        try {
            $actividad->setFechaInicio(new \DateTime($dto->fecha_inicio));
        } catch (\Exception $e) {
            return $this->json(['error' => 'Fecha inválida'], Response::HTTP_BAD_REQUEST);
        }

        // 3. Asignar Relaciones (ODS y Tipos)
        foreach ($dto->odsIds as $idOds) {
            $ods = $odsRepo->find($idOds);
            if ($ods) $actividad->addOd($ods);
        }

        foreach ($dto->tiposIds as $idTipo) {
            $tipo = $tipoRepo->find($idTipo);
            if ($tipo) $actividad->addTiposVoluntariado($tipo);
        }

        // 4. Guardar
        try {
            $entityManager->persist($actividad);
            $entityManager->flush();

            return $this->json(ActividadResponseDTO::fromEntity($actividad), Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al crear actividad: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ========================================================================
    // 3. ACTUALIZAR ACTIVIDAD (PUT) - Usando ActividadUpdateDTO
    // ========================================================================
    #[Route('/actividades/{id}', name: 'actualizar_actividad', methods: ['PUT'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: new Model(type: ActividadUpdateDTO::class) // <--- DTO Sin id_organizacion
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Actividad actualizada',
        content: new OA\JsonContent(
            ref: new Model(type: ActividadResponseDTO::class)
        )
    )]
    public function actualizar(
        int $id,
        #[MapRequestPayload] ActividadUpdateDTO $dto,
        ActividadRepository $actividadRepository,
        ODSRepository $odsRepo,
        TipoVoluntariadoRepository $tipoRepo,
        EntityManagerInterface $entityManager
    ): JsonResponse {

        $actividad = $actividadRepository->find($id);
        if (!$actividad) {
            return $this->json(['error' => 'Actividad no encontrada'], Response::HTTP_NOT_FOUND);
        }

        // 1. Actualizar campos (Seguro: No tocamos id_organizacion)
        $actividad->setTitulo($dto->titulo);
        $actividad->setDescripcion($dto->descripcion);
        $actividad->setUbicacion($dto->ubicacion);
        $actividad->setDuracionHoras($dto->duracion_horas);
        $actividad->setCupoMaximo($dto->cupo_maximo);
        try {
            $actividad->setFechaInicio(new \DateTime($dto->fecha_inicio));
        } catch (\Exception $e) {
            return $this->json(['error' => 'Fecha inválida'], 400);
        }

        // 2. Sincronizar ODS (Borrar antiguos y poner nuevos)
        foreach ($actividad->getOds() as $odExisting) {
            $actividad->removeOd($odExisting);
        }
        foreach ($dto->odsIds as $idOds) {
            $ods = $odsRepo->find($idOds);
            if ($ods) $actividad->addOd($ods);
        }

        // 3. Sincronizar Tipos
        foreach ($actividad->getTiposVoluntariado() as $tipoExisting) {
            $actividad->removeTiposVoluntariado($tipoExisting);
        }
        foreach ($dto->tiposIds as $idTipo) {
            $tipo = $tipoRepo->find($idTipo);
            if ($tipo) $actividad->addTiposVoluntariado($tipo);
        }

        $entityManager->flush();

        return $this->json(ActividadResponseDTO::fromEntity($actividad), Response::HTTP_OK);
    }

    // ========================================================================
    // 4. ELIMINAR ACTIVIDAD (DELETE) - USANDO SP (Soft Delete)
    // ========================================================================
    #[Route('/actividades/{id}', name: 'eliminar_actividad', methods: ['DELETE'])]
    #[OA\Response(response: 200, description: 'Actividad marcada como cancelada/eliminada')]
    public function eliminar(
        int $id,
        ActividadRepository $actividadRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $actividad = $actividadRepository->find($id);
        if (!$actividad) {
            return $this->json(['error' => 'Actividad no encontrada'], Response::HTTP_NOT_FOUND);
        }

        if ($actividad->getDeletedAt() !== null) {
            return $this->json(['mensaje' => 'La actividad ya estaba eliminada previamente'], Response::HTTP_OK);
        }

        // Llamada al Stored Procedure
        $conn = $entityManager->getConnection();
        try {
            $conn->executeStatement('EXEC SP_SoftDelete_Actividad @id_actividad = :id', ['id' => $id]);
            return $this->json(['mensaje' => 'Actividad cancelada y eliminada correctamente'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al eliminar la actividad: ' . $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // 5. DETALLE (GET ONE)
    // ========================================================================
    #[Route('/actividades/{id}', name: 'detalle_actividad', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Detalle completo de la actividad',
        content: new OA\JsonContent(
            ref: new Model(type: ActividadResponseDTO::class)
        )
    )]
    public function detalle(?Actividad $actividad = null, EntityManagerInterface $em): JsonResponse
    {
        if (!$actividad) {
            return $this->json(['error' => 'Actividad no encontrada'], Response::HTTP_NOT_FOUND);
        }

        $dto = ActividadResponseDTO::fromEntity($actividad);

        // Enriquecer con Imagen (buscando en repositorio inverso)
        $imagenEntity = $em->getRepository(ImagenActividad::class)->findOneBy(['actividad' => $actividad->getId()]);
        if ($imagenEntity) {
            $dto->imagen_actividad = $imagenEntity->getUrlImagen();
        }

        return $this->json($dto, Response::HTTP_OK);
    }

    // ========================================================================
    // 6. AÑADIR IMAGEN (POST)
    // ========================================================================
    #[Route('/actividades/{id}/imagenes', name: 'add_imagen_actividad', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'url_imagen', type: 'string'),
                new OA\Property(property: 'descripcion', type: 'string')
            ]
        )
    )]
    public function addImagen(
        int $id,
        Request $request,
        ActividadRepository $actividadRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $actividad = $actividadRepo->find($id);
        if (!$actividad) {
            return $this->json(['error' => 'Actividad no encontrada'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (empty($data['url_imagen'])) {
            return $this->json(['error' => 'Falta la URL de la imagen'], Response::HTTP_BAD_REQUEST);
        }

        $imagen = new ImagenActividad();
        $imagen->setActividad($actividad);
        $imagen->setUrlImagen($data['url_imagen']);
        $imagen->setDescripcionPieFoto($data['descripcion'] ?? null);

        try {
            $em->persist($imagen);
            $em->flush();
            return $this->json([
                'mensaje' => 'Imagen añadida correctamente',
                'id_imagen' => $imagen->getId(),
                'url' => $imagen->getUrlImagen()
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al guardar la imagen'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    // ========================================================================
    // 7. LISTAR INSCRIPCIONES (GET) - Para coordinadores
    // ========================================================================
    #[Route('/actividades/{id}/participantes-detalle', name: 'listar_inscripciones_actividad_detalle', methods: ['GET'])]
    public function obtenerInscripciones(
        int $id,
        ActividadRepository $actRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $actividad = $actRepo->find($id);
        if (!$actividad) return $this->json(['error' => 'No encontrada'], 404);

        $conn = $em->getConnection();
        // SQL directo para obtener datos de voluntarios rápidamente
        // SQL corregido: INSCRIPCION tiene PK compuesta, no id_inscripcion
        $sql = "
            SELECT 
                CONCAT(i.id_voluntario, '-', i.id_actividad) as id_inscripcion,
                i.fecha_solicitud as fecha_solicitud,
                i.estado_solicitud as estado_solicitud,
                v.id_usuario as id_voluntario,
                v.nombre as nombre,
                v.apellidos as apellidos,
                u.correo as email,
                v.telefono as telefono
            FROM INSCRIPCION i
            INNER JOIN VOLUNTARIO v ON i.id_voluntario = v.id_usuario
            INNER JOIN USUARIO u ON v.id_usuario = u.id_usuario
            WHERE i.id_actividad = :id
            ORDER BY i.fecha_solicitud DESC
        ";

        try {
            $rawInscritos = $conn->executeQuery($sql, ['id' => $id])->fetchAllAssociative();
            
            // GARANTIZAR MINÚSCULAS: Normalizamos las claves del array
            $inscritos = array_map(function($row) {
                return array_change_key_case($row, CASE_LOWER);
            }, $rawInscritos);

            return $this->json($inscritos);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al obtener inscripciones: ' . $e->getMessage()], 500);
        }
        }


    // ========================================================================
    // 8. GESTIONAR INSCRIPCIÓN (PATCH) - Aceptar/Rechazar
    // ========================================================================
    #[Route('/actividades/{idActividad}/inscripciones/{idVoluntario}', name: 'gestionar_inscripcion', methods: ['PATCH'])]
    public function gestionarEstadoInscripcion(
        int $idActividad,
        int $idVoluntario,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $conn = $em->getConnection();
        $data = json_decode($request->getContent(), true);
        $nuevoEstado = $data['estado'] ?? null;

        if (!$nuevoEstado) {
            return $this->json(['error' => 'Falta el estado'], 400);
        }

        try {
            $sql = "UPDATE INSCRIPCION SET estado_solicitud = :estado WHERE id_actividad = :idAct AND id_voluntario = :idVol";
            $count = $conn->executeStatement($sql, [
                'estado' => $nuevoEstado,
                'idAct' => $idActividad,
                'idVol' => $idVoluntario // id_voluntario == id_usuario en BBDD
            ]);

            if ($count === 0) {
                return $this->json(['error' => 'Inscripción no encontrada'], 404);
            }
            return $this->json(['mensaje' => 'Estado actualizado correctamente']);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al actualizar inscripción'], 500);
        }
    }

    // ========================================================================
    // 9. ELIMINAR INSCRIPCIÓN (DELETE) - "Quitar" voluntario
    // ========================================================================
    #[Route('/actividades/{idActividad}/inscripciones/{idVoluntario}', name: 'eliminar_inscripcion_admin', methods: ['DELETE'])]
    public function eliminarInscripcion(
        int $idActividad,
        int $idVoluntario,
        EntityManagerInterface $em
    ): JsonResponse {
        $conn = $em->getConnection();
        try {
            $sql = "DELETE FROM INSCRIPCION WHERE id_actividad = :idAct AND id_voluntario = :idVol";
            $count = $conn->executeStatement($sql, [
                'idAct' => $idActividad,
                'idVol' => $idVoluntario
            ]);

            if ($count === 0) {
                return $this->json(['error' => 'Inscripción no encontrada'], 404);
            }
            return $this->json(['mensaje' => 'Inscripción eliminada correctamente']);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al eliminar inscripción'], 500);
        }
    }
}
