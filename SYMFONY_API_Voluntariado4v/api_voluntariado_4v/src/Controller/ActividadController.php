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
            return $this->json(['error' => 'Usuario Organización no encontrado'], Response::HTTP_NOT_FOUND);
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

            // 5. Devolver DTO de Respuesta (No la entidad circular)
            // Necesitas tener el método estático `fromEntity` en tu ActividadResponseDTO (como te enseñé antes)
            // Si no lo tienes, créalo, o mapea manualmente aquí. Asumo que lo tienes.
            // Si te da error aquí, avísame para pasarte el mapper.
            return $this->json($this->mapToResponse($actividad), Response::HTTP_CREATED);
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

        return $this->json($this->mapToResponse($actividad), Response::HTTP_OK);
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
            return $this->json(['error' => 'Error al eliminar actividad: ' . $e->getMessage()], 500);
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
    public function detalle(?Actividad $actividad = null): JsonResponse
    {
        if (!$actividad) {
            return $this->json(['error' => 'Actividad no encontrada'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->mapToResponse($actividad), Response::HTTP_OK);
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
            return $this->json(['error' => 'Error al guardar imagen'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ========================================================================
    // HELPER: Mapper Manual (Si no tienes el fromEntity estático en el DTO)
    // ========================================================================
    private function mapToResponse(Actividad $act): ActividadResponseDTO
    {
        // Esto es un parche por si tu DTO ActividadResponseDTO no tiene el método estático fromEntity.
        // Si ya lo tiene, usa ActividadResponseDTO::fromEntity($act) directamente en el return.

        // Mapeo de ODS a array/DTOs (según tu definición de ResponseDTO)
        $odsList = [];
        foreach ($act->getOds() as $o) {
            // Asumo que tienes un OdsDTO o usas arrays simples. Ajusta según tu ResponseDTO.
            // Por simplicidad envío objetos anónimos o lo que espere tu DTO.
            $odsList[] = ['id' => $o->getIdOds(), 'nombre' => $o->getNombre()];
        }

        // Mapeo de Tipos
        $tiposList = [];
        foreach ($act->getTiposVoluntariado() as $t) {
            $tiposList[] = ['id' => $t->getIdTipo(), 'nombre' => $t->getNombreTipo()];
        }

        $org = $act->getOrganizacion();

        return new ActividadResponseDTO(
            id: $act->getId(),
            titulo: $act->getTitulo(),
            descripcion: $act->getDescripcion(),
            fecha_inicio: $act->getFechaInicio()->format('Y-m-d H:i:s'),
            duracion_horas: $act->getDuracionHoras(),
            cupo_maximo: $act->getCupoMaximo(),
            inscritos_confirmados: 0, // O calcular count($act->getInscripciones()...)
            ubicacion: $act->getUbicacion() ?? 'No definida',
            estado_publicacion: $act->getEstadoPublicacion(),
            nombre_organizacion: $org ? $org->getNombre() : 'Desconocida',
            img_organizacion: null, // Se obtendrá de Firebase/Google
            ods: $odsList,
            tipos: $tiposList
        );
    }
}
