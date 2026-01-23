<?php

namespace App\Controller;

use App\Entity\Actividad;
use App\Entity\Coordinador;
use App\Entity\Usuario;
use App\Model\Coordinador\CoordinadorCreateDTO;
use App\Model\Coordinador\CoordinadorResponseDTO;
use App\Model\Coordinador\CoordinadorUpdateDTO;
use App\Repository\ActividadRepository;
use App\Repository\RolRepository;
use App\Repository\UsuarioRepository;
use App\Repository\CoordinadorRepository;
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
#[OA\Tag(name: 'Coordinadores', description: 'Gestión interna, Dashboard y Moderación Global')]
final class CoordinadorController extends AbstractController
{
    private function checkCoordinador(Request $request, UsuarioRepository $repo): ?Usuario
    {
        $adminId = $request->headers->get('X-Admin-Id');
        if (!$adminId) return null;

        $user = $repo->find($adminId);
        if ($user && in_array($user->getRol()->getNombre(), ['Coordinador'])) {
            return $user;
        }
        return null;
    }

    // ========================================================================
    // 1. DASHBOARD GLOBAL (GET)
    // ========================================================================
    #[Route('/coord/stats', name: 'coord_stats', methods: ['GET'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', description: 'ID de Coordinador', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Métricas del dashboard global',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'titulo', type: 'string'),
            new OA\Property(property: 'fecha_generacion', type: 'string'),
            new OA\Property(property: 'metricas', properties: [
                new OA\Property(property: 'voluntarios_activos', type: 'integer'),
                new OA\Property(property: 'organizaciones_activas', type: 'integer'),
                new OA\Property(property: 'coordinadores_activos', type: 'integer'),
                new OA\Property(property: 'actividades_publicadas', type: 'integer'),
                new OA\Property(property: 'voluntarios_pendientes', type: 'integer'),
                new OA\Property(property: 'actividades_pendientes', type: 'integer')
            ], type: 'object')
        ])
    )]
    public function dashboard(
        Request $request,
        UsuarioRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {

        if (!$this->checkCoordinador($request, $userRepo)) {
            return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);
        }

        $conn = $em->getConnection();

        try {
            $rawStats = [];
            try {
                $rawStats = $conn->executeQuery('EXEC SP_Dashboard_Stats')->fetchAssociative();
            } catch (\Exception $e) {
                $rawStats = [
                    'voluntarios_activos' => $conn->fetchOne("SELECT COUNT(*) FROM USUARIO u JOIN ROL r ON u.id_rol = r.id_rol WHERE UPPER(r.nombre_rol) = 'VOLUNTARIO' AND u.deleted_at IS NULL"),
                    'organizaciones_activas' => $conn->fetchOne("SELECT COUNT(*) FROM USUARIO u JOIN ROL r ON u.id_rol = r.id_rol WHERE UPPER(r.nombre_rol) = 'ORGANIZACION' AND u.deleted_at IS NULL"),
                    'coordinadores_activos' => $conn->fetchOne("SELECT COUNT(*) FROM USUARIO u JOIN ROL r ON u.id_rol = r.id_rol WHERE UPPER(r.nombre_rol) = 'COORDINADOR' AND u.deleted_at IS NULL"),
                    'actividades_publicadas' => $conn->fetchOne("SELECT COUNT(*) FROM ACTIVIDAD WHERE UPPER(estado_publicacion) = 'PUBLICADA' AND deleted_at IS NULL"),
                    'voluntarios_pendientes' => $conn->fetchOne("SELECT COUNT(*) FROM USUARIO WHERE UPPER(estado_cuenta) = 'PENDIENTE' AND deleted_at IS NULL"),
                    'actividades_pendientes' => $conn->fetchOne("SELECT COUNT(*) FROM ACTIVIDAD WHERE UPPER(estado_publicacion) IN ('EN REVISION', 'PENDIENTE') AND deleted_at IS NULL")
                ];
            }

            $safeStats = [
                'voluntarios_activos'    => (int)($rawStats['voluntarios_activos'] ?? $rawStats['total_usuarios'] ?? 0),
                'organizaciones_activas' => (int)($rawStats['organizaciones_activas'] ?? $rawStats['total_organizaciones'] ?? 0),
                'coordinadores_activos'  => (int)($rawStats['coordinadores_activos'] ?? $conn->fetchOne("SELECT COUNT(*) FROM USUARIO u JOIN ROL r ON u.id_rol = r.id_rol WHERE r.nombre_rol = 'Coordinador' AND u.deleted_at IS NULL")),
                'actividades_publicadas' => (int)($rawStats['actividades_publicadas'] ?? 0),
                'voluntarios_pendientes' => (int)($rawStats['voluntarios_pendientes'] ?? $rawStats['inscripciones_pendientes'] ?? 0),
                'actividades_pendientes' => (int)($rawStats['actividades_pendientes'] ?? $rawStats['actividades_revision'] ?? 0),
            ];

            return $this->json([
                'titulo' => 'Panel de Control General',
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'metricas' => $safeStats
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error calculando estadísticas: ' . $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // 2. REGISTRAR COORDINADOR (POST)
    // ========================================================================
    #[Route('/coordinadores', name: 'registro_coordinador', methods: ['POST'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: CoordinadorCreateDTO::class)))]
    public function registrar(
        Request $request,
        #[MapRequestPayload] CoordinadorCreateDTO $dto,
        EntityManagerInterface $entityManager,
        RolRepository $rolRepository,
        UsuarioRepository $userRepo
    ): JsonResponse {

        if (!$this->checkCoordinador($request, $userRepo)) {
            return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);
        }

        $entityManager->beginTransaction();
        try {
            $usuario = new Usuario();
            $usuario->setCorreo($dto->correo);
            $usuario->setGoogleId($dto->google_id);
            $usuario->setEstadoCuenta('Activa');

            $rolCoord = $rolRepository->findOneBy(['nombre' => 'Coordinador']);
            if (!$rolCoord) throw new \Exception("Rol 'Coordinador' no configurado.");
            $usuario->setRol($rolCoord);

            $entityManager->persist($usuario);
            $entityManager->flush();

            $coord = new Coordinador();
            $coord->setUsuario($usuario);
            $coord->setNombre($dto->nombre);
            $coord->setApellidos($dto->apellidos);
            $coord->setTelefono($dto->telefono);

            $entityManager->persist($coord);
            $entityManager->flush();
            $entityManager->commit();

            return $this->json(CoordinadorResponseDTO::fromEntity($coord), Response::HTTP_CREATED);
        } catch (\Exception $e) {
            $entityManager->rollback();
            return $this->json(['error' => 'Error al registrar: ' . $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // 3. VER PERFIL COORDINADOR (GET)
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'get_coordinador', methods: ['GET'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function getOne(int $id, Request $request, UsuarioRepository $userRepo, CoordinadorRepository $coordRepo): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) return $this->json(['error' => 'Coordinador no encontrado'], Response::HTTP_NOT_FOUND);

        $coord = $coordRepo->findOneBy(['usuario' => $usuario]);
        if (!$coord) {
            return $this->json([
                'id_usuario' => $usuario->getId(),
                'nombre' => 'Usuario',
                'apellidos' => $usuario->getId(),
                'telefono' => '',
                'correo' => $usuario->getCorreo()
            ], Response::HTTP_OK);
        }
        return $this->json(CoordinadorResponseDTO::fromEntity($coord), Response::HTTP_OK);
    }

    // ========================================================================
    // 4. ACTUALIZAR PERFIL COORDINADOR (PUT)
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'actualizar_coordinador', methods: ['PUT'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: CoordinadorUpdateDTO::class)))]
    public function actualizar(int $id, #[MapRequestPayload] CoordinadorUpdateDTO $dto, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $usuario = $userRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);

        $coord = $em->getRepository(Coordinador::class)->findOneBy(['usuario' => $usuario]);
        if (!$coord) return $this->json(['error' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);

        $coord->setNombre($dto->nombre);
        $coord->setApellidos($dto->apellidos);
        $coord->setTelefono($dto->telefono);
        $coord->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json(CoordinadorResponseDTO::fromEntity($coord), Response::HTTP_OK);
    }

    // ========================================================================
    // 5. GESTIÓN DE ESTADO DE USUARIOS (PATCH)
    // ========================================================================
    #[Route('/coord/{rol}/{id}/estado', name: 'coord_cambiar_estado_usuario', methods: ['PATCH'], requirements: ['rol' => 'voluntarios|organizaciones'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function cambiarEstadoUsuario(int $id, string $rol, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $data = json_decode($request->getContent(), true);
        $nuevoEstado = $data['estado'] ?? null;

        $usuario = $userRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);

        $usuario->setEstadoCuenta($nuevoEstado);
        $usuario->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json(['mensaje' => 'Estado actualizado a ' . $nuevoEstado], Response::HTTP_OK);
    }

    // ========================================================================
    // 6. LISTAR ACTIVIDADES GLOBALES (GET)
    // ========================================================================
    #[Route('/coord/actividades', name: 'coord_listar_actividades', methods: ['GET'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function listarActividadesGlobal(Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $conn = $em->getConnection();
        $sql = "
            SELECT 
                a.id_actividad, a.titulo, a.descripcion, a.fecha_inicio, a.duracion_horas, 
                a.cupo_maximo, a.ubicacion, a.estado_publicacion, a.img_actividad as imagen_actividad,
                (SELECT COUNT(*) FROM INSCRIPCION i WHERE i.id_actividad = a.id_actividad AND i.estado_solicitud = 'Aceptada') as inscritos_confirmados,
                COALESCE(o.nombre, 'Organización Desconocida') as nombre_organizacion,
                COALESCE(u.id_usuario, 0) as id_organizacion
            FROM ACTIVIDAD a
            LEFT JOIN ORGANIZACION o ON a.id_organizacion = o.id_usuario
            LEFT JOIN USUARIO u ON o.id_usuario = u.id_usuario
            WHERE (a.deleted_at IS NULL OR a.estado_publicacion IN ('Cancelada', 'Rechazada'))
            ORDER BY a.fecha_inicio DESC
        ";

        try {
            $actividades = $conn->fetchAllAssociative($sql);
            return $this->json($actividades, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // 7. CAMBIAR ESTADO ACTIVIDAD (PATCH)
    // ========================================================================
    #[Route('/coord/actividades/{id}/estado', name: 'coord_cambiar_estado_actividad', methods: ['PATCH'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function cambiarEstadoActividad(int $id, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $data = json_decode($request->getContent(), true);
        $actividad = $em->getRepository(Actividad::class)->find($id);
        if (!$actividad) return $this->json(['error' => 'Actividad no encontrada'], Response::HTTP_NOT_FOUND);

        $actividad->setEstadoPublicacion($data['estado']);
        $actividad->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json(['mensaje' => 'Estado actualizado'], Response::HTTP_OK);
    }

    // ========================================================================
    // 8. BORRAR ACTIVIDAD (DELETE)
    // ========================================================================
    #[Route('/coord/actividades/{id}', name: 'coord_borrar_actividad', methods: ['DELETE'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function borrarActividadCoord(int $id, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $sql = 'EXEC SP_SoftDelete_Actividad @id_actividad = :id';
        $em->getConnection()->executeStatement($sql, ['id' => $id]);
        return $this->json(['mensaje' => 'Actividad eliminada'], Response::HTTP_OK);
    }

    // ========================================================================
    // 9. EDITAR ACTIVIDAD (PUT)
    // ========================================================================
    // ========================================================================
    // 10. LISTAR INSCRIPCIONES PENDIENTES (GET)
    // ========================================================================
    #[Route('/coord/inscripciones/pendientes', name: 'coord_listar_inscripciones', methods: ['GET'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function listarInscripcionesPendientes(Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $conn = $em->getConnection();
        $sql = "
            SELECT 
                i.id_actividad, 
                i.id_voluntario, 
                i.fecha_solicitud, 
                i.estado_solicitud,
                v.nombre as nombre_voluntario, 
                v.apellidos as apellidos_voluntario, 
                u.correo as email_voluntario,
                a.titulo as titulo_actividad,
                a.img_actividad as imagen_actividad
            FROM INSCRIPCION i
            JOIN USUARIO u ON i.id_voluntario = u.id_usuario
            JOIN VOLUNTARIO v ON i.id_voluntario = v.id_usuario
            JOIN ACTIVIDAD a ON i.id_actividad = a.id_actividad
            WHERE UPPER(i.estado_solicitud) = 'PENDIENTE'
            ORDER BY i.fecha_solicitud ASC
        ";

        try {
            $inscripciones = $conn->fetchAllAssociative($sql);
            return $this->json($inscripciones, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/coord/actividades/{id}', name: 'coord_editar_actividad', methods: ['PUT'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function editarActividadCoord(int $id, Request $request, ActividadRepository $repo, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $actividad = $repo->find($id);
        if (!$actividad) return $this->json(['error' => 'No encontrada'], 404);

        $data = json_decode($request->getContent(), true);
        if (isset($data['titulo'])) $actividad->setTitulo($data['titulo']);
        if (isset($data['descripcion'])) $actividad->setDescripcion($data['descripcion']);
        if (isset($data['cupo_maximo'])) $actividad->setCupoMaximo($data['cupo_maximo']);

        $em->flush();
        return $this->json(['mensaje' => 'Editada'], 200);
    }

    // ========================================================================
    // 10. ELIMINAR COORDINADOR (DELETE)
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'borrar_usuario_coord', methods: ['DELETE'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function eliminar(int $id, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $sql = 'EXEC SP_SoftDelete_Usuario @id_usuario = :id';
        $em->getConnection()->executeStatement($sql, ['id' => $id]);
        return $this->json(['mensaje' => 'Cuenta cerrada'], Response::HTTP_OK);
    }
    // ========================================================================
    // 11. CAMBIAR ESTADO USUARIO (PATCH)
    // ========================================================================
    #[Route('/coord/usuarios/{id}/estado', name: 'coord_update_user_status', methods: ['PATCH'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'estado', type: 'string', example: 'Bloqueada')]))]
    public function updateEstadoUsuario(
        int $id, 
        Request $request, 
        UsuarioRepository $repo, 
        EntityManagerInterface $em
    ): JsonResponse {
        if (!$this->checkCoordinador($request, $repo)) {
            return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);
        }

        $usuario = $repo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], 404);

        $data = json_decode($request->getContent(), true);
        $nuevoEstado = $data['estado'] ?? null;

        if (!in_array($nuevoEstado, ['Activa', 'Bloqueada', 'Pendiente', 'Rechazada'])) {
            return $this->json(['error' => 'Estado inválido'], 400);
        }

        $usuario->setEstadoCuenta($nuevoEstado);
        $em->flush();

        return $this->json(['mensaje' => "Estado actualizado a $nuevoEstado"], 200);
    }
}
