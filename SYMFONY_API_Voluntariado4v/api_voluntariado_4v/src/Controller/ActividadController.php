<?php

namespace App\Controller;

use App\Entity\Actividad;
use App\Entity\ImagenActividad; // <--- Importante añadir esto
use App\Entity\Organizacion;
use App\Repository\ActividadRepository;
use App\Repository\ODSRepository;
use App\Repository\TipoVoluntariadoRepository;
use App\Repository\UsuarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api', name: 'api_')]
#[OA\Tag(name: 'Actividades', description: 'Gestión de ofertas de voluntariado')]
final class ActividadController extends AbstractController
{
    // ========================================================================
    // 1. LISTAR ACTIVIDADES (GET) - FILTROS SQL + VISTA
    // ========================================================================
    #[Route('/actividades', name: 'listar_actividades', methods: ['GET'])]
    #[OA\Parameter(name: 'ods_id', description: 'Filtrar por ID de ODS', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'tipo_id', description: 'Filtrar por ID de Tipo Voluntariado', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Catálogo de actividades publicadas (Vista SQL)',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'object'))
    )]
    public function index(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $odsId = $request->query->get('ods_id');
        $tipoId = $request->query->get('tipo_id');

        $conn = $em->getConnection();
        $qb = $conn->createQueryBuilder();

        // Usamos la VISTA SQL (VW_Actividades_Publicadas)
        // Ventaja: Ya filtra deleted_at IS NULL y estado = 'Publicada'
        $qb->select('*')->from('VW_Actividades_Publicadas');

        // Filtro Dinámico 1: Por ODS
        if ($odsId) {
            $qb->andWhere('id_actividad IN (SELECT id_actividad FROM ACTIVIDAD_ODS WHERE id_ods = :ods)')
                ->setParameter('ods', $odsId);
        }

        // Filtro Dinámico 2: Por Tipo de Voluntariado
        if ($tipoId) {
            $qb->andWhere('id_actividad IN (SELECT id_actividad FROM ACTIVIDAD_TIPO WHERE id_tipo = :tipo)')
                ->setParameter('tipo', $tipoId);
        }

        try {
            // fetchAllAssociative devuelve un array de arrays asociativos (snake_case tal cual viene de la BBDD)
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
    // 2. CREAR ACTIVIDAD (POST)
    // ========================================================================
    #[Route('/actividades', name: 'crear_actividad', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id_organizacion', type: 'integer', description: 'ID del Usuario Organización'),
                new OA\Property(property: 'titulo', type: 'string'),
                new OA\Property(property: 'descripcion', type: 'string'),
                new OA\Property(property: 'ubicacion', type: 'string'),
                new OA\Property(property: 'duracion_horas', type: 'integer'),
                new OA\Property(property: 'cupo_maximo', type: 'integer'),
                new OA\Property(property: 'fecha_inicio', type: 'string', format: 'date-time'),
                new OA\Property(property: 'ods_ids', type: 'array', items: new OA\Items(type: 'integer')),
                new OA\Property(property: 'tipo_ids', type: 'array', items: new OA\Items(type: 'integer'))
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Actividad creada (En revisión)')]
    public function crear(
        Request $request,
        EntityManagerInterface $entityManager,
        UsuarioRepository $userRepo,
        ODSRepository $odsRepo,
        TipoVoluntariadoRepository $tipoRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // A. Validaciones básicas
        if (!isset($data['id_organizacion'], $data['titulo'], $data['fecha_inicio'], $data['duracion_horas'], $data['cupo_maximo'])) {
            return $this->json(['error' => 'Faltan datos obligatorios'], Response::HTTP_BAD_REQUEST);
        }

        // B. Buscar la Organización dueña
        $usuarioOrg = $userRepo->find($data['id_organizacion']);
        if (!$usuarioOrg) return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);

        // OJO: Asumimos que la entidad Organizacion tiene relación OneToOne con Usuario o comparte ID
        $organizacion = $entityManager->getRepository(Organizacion::class)->findOneBy(['usuario' => $usuarioOrg]);

        // Si no usas relación OneToOne y usan el mismo ID (herencia), usa esto:
        // $organizacion = $entityManager->getRepository(Organizacion::class)->find($data['id_organizacion']);

        if (!$organizacion) {
            return $this->json(['error' => 'Perfil de organización no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // C. Crear el objeto Actividad
        $actividad = new Actividad();
        $actividad->setOrganizacion($organizacion);
        $actividad->setTitulo($data['titulo']);
        $actividad->setDescripcion($data['descripcion'] ?? null);
        $actividad->setUbicacion($data['ubicacion'] ?? null);
        $actividad->setDuracionHoras($data['duracion_horas']);
        $actividad->setCupoMaximo($data['cupo_maximo']);
        $actividad->setEstadoPublicacion('En revision');

        try {
            $actividad->setFechaInicio(new \DateTime($data['fecha_inicio']));
        } catch (\Exception $e) {
            return $this->json(['error' => 'Formato de fecha inválido'], Response::HTTP_BAD_REQUEST);
        }

        // E. Asignar ODS
        if (!empty($data['ods_ids']) && is_array($data['ods_ids'])) {
            foreach ($data['ods_ids'] as $idOds) {
                $ods = $odsRepo->find($idOds);
                if ($ods) $actividad->addOd($ods);
            }
        }

        // F. Asignar Tipos
        if (!empty($data['tipo_ids']) && is_array($data['tipo_ids'])) {
            foreach ($data['tipo_ids'] as $idTipo) {
                $tipo = $tipoRepo->find($idTipo);
                if ($tipo) $actividad->addTiposVoluntariado($tipo);
            }
        }

        try {
            $entityManager->persist($actividad);
            $entityManager->flush();
            // Usamos groups para serializar solo lo necesario
            return $this->json($actividad, Response::HTTP_CREATED, [], ['groups' => 'actividad:read']);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al crear actividad: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ========================================================================
    // 3. ACTUALIZAR ACTIVIDAD (PUT)
    // ========================================================================
    #[Route('/actividades/{id}', name: 'actualizar_actividad', methods: ['PUT'])]
    public function actualizar(
        int $id,
        Request $request,
        ActividadRepository $actividadRepository,
        ODSRepository $odsRepo,
        TipoVoluntariadoRepository $tipoRepo,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $actividad = $actividadRepository->find($id);
        if (!$actividad) {
            return $this->json(['error' => 'Actividad no encontrada'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['titulo'])) $actividad->setTitulo($data['titulo']);
        if (isset($data['descripcion'])) $actividad->setDescripcion($data['descripcion']);
        if (isset($data['ubicacion'])) $actividad->setUbicacion($data['ubicacion']);
        if (isset($data['duracion_horas'])) $actividad->setDuracionHoras($data['duracion_horas']);
        if (isset($data['cupo_maximo'])) $actividad->setCupoMaximo($data['cupo_maximo']);

        if (isset($data['fecha_inicio'])) {
            try {
                $actividad->setFechaInicio(new \DateTime($data['fecha_inicio']));
            } catch (\Exception $e) {
                return $this->json(['error' => 'Formato de fecha inválido'], Response::HTTP_BAD_REQUEST);
            }
        }

        // Sincronizar ODS
        if (isset($data['ods_ids']) && is_array($data['ods_ids'])) {
            // Borrar existentes
            foreach ($actividad->getOds() as $odExisting) {
                $actividad->removeOd($odExisting);
            }
            // Añadir nuevos
            foreach ($data['ods_ids'] as $idOds) {
                $ods = $odsRepo->find($idOds);
                if ($ods) $actividad->addOd($ods);
            }
        }

        // Sincronizar Tipos
        if (isset($data['tipo_ids']) && is_array($data['tipo_ids'])) {
            foreach ($actividad->getTiposVoluntariado() as $tipoExisting) {
                $actividad->removeTiposVoluntariado($tipoExisting);
            }
            foreach ($data['tipo_ids'] as $idTipo) {
                $tipo = $tipoRepo->find($idTipo);
                if ($tipo) $actividad->addTiposVoluntariado($tipo);
            }
        }

        $entityManager->flush(); // El trigger actualizará updated_at en BBDD

        return $this->json($actividad, Response::HTTP_OK, [], ['groups' => 'actividad:read']);
    }

    // ========================================================================
    // 4. ELIMINAR ACTIVIDAD (DELETE) - USANDO SP
    // ========================================================================
    #[Route('/actividades/{id}', name: 'eliminar_actividad', methods: ['DELETE'])]
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

        // Llamada al Stored Procedure para Soft Delete seguro
        $conn = $entityManager->getConnection();
        try {
            $conn->executeStatement('EXEC SP_SoftDelete_Actividad @id_actividad = :id', ['id' => $id]);
            return $this->json(['mensaje' => 'Actividad cancelada y eliminada correctamente'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al eliminar actividad: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    // ========================================================================
    // 5. DETALLE (GET ONE)
    // ========================================================================
    #[Route('/actividades/{id}', name: 'detalle_actividad', methods: ['GET'])]
    public function detalle(?Actividad $actividad = null): JsonResponse
    {
        if (!$actividad) {
            return $this->json(['error' => 'Actividad no encontrada'], Response::HTTP_NOT_FOUND);
        }
        // Doctrine ParamConverter inyecta $actividad automáticamente si el ID coincide
        return $this->json($actividad, Response::HTTP_OK, [], ['groups' => 'actividad:read']);
    }

    // ========================================================================
    // 6. AÑADIR IMAGEN A LA GALERÍA (POST)
    // ========================================================================
    #[Route('/actividades/{id}/imagenes', name: 'add_imagen_actividad', methods: ['POST'])]
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

        $imagen = new ImagenActividad(); // Usando el 'use' de arriba
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
            return $this->json(
                ['error' => 'Error al guardar la imagen: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
