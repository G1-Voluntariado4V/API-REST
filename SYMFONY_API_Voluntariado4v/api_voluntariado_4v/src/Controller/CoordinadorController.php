<?php

namespace App\Controller;

use App\Entity\Actividad;
use App\Entity\Coordinador;
use App\Entity\Usuario;
// DTOs
use App\Model\Coordinador\CoordinadorCreateDTO;
use App\Model\Coordinador\CoordinadorResponseDTO;
use App\Model\Coordinador\CoordinadorUpdateDTO;
// Repositorios
use App\Repository\ActividadRepository;
use App\Repository\RolRepository;
use App\Repository\UsuarioRepository;
use App\Repository\CoordinadorRepository;
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
    // 1. DASHBOARD GLOBAL (Estadísticas) - CORREGIDO PARA JSON SEGURO
    // ========================================================================
    #[Route('/coord/stats', name: 'coord_stats', methods: ['GET'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', description: 'ID de Coordinador', required: true, schema: new OA\Schema(type: 'integer'))]
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
            // Intentamos ejecutar el SP
            $rawStats = [];
            try {
                $rawStats = $conn->executeQuery('EXEC SP_Dashboard_Stats')->fetchAssociative();
            } catch (\Exception $e) {
                // Fallback a consultas manuales si el SP falla o no existe
                $rawStats = [
                    'voluntarios_activos' => $conn->fetchOne("SELECT COUNT(*) FROM USUARIO u JOIN ROL r ON u.id_rol = r.id_rol WHERE r.nombre = 'Voluntario' AND u.deleted_at IS NULL"),
                    'organizaciones_activas' => $conn->fetchOne("SELECT COUNT(*) FROM USUARIO u JOIN ROL r ON u.id_rol = r.id_rol WHERE r.nombre = 'Organizacion' AND u.deleted_at IS NULL"),
                    'actividades_publicadas' => $conn->fetchOne("SELECT COUNT(*) FROM ACTIVIDAD WHERE estado_publicacion = 'Publicada' AND deleted_at IS NULL"),
                    'voluntarios_pendientes' => $conn->fetchOne("SELECT COUNT(*) FROM USUARIO WHERE estado_cuenta = 'Pendiente' AND deleted_at IS NULL"),
                    'actividades_pendientes' => $conn->fetchOne("SELECT COUNT(*) FROM ACTIVIDAD WHERE estado_publicacion = 'En revision' AND deleted_at IS NULL")
                ];
            }

            // ASEGURAR NOMBRES DE CLAVES PARA ANDROID (Mapeo Seguro)
            // Esto arregla el problema de "0" si el SP devuelve nombres de columnas diferentes.
            $safeStats = [
                'voluntarios_activos'    => (int)($rawStats['voluntarios_activos'] ?? $rawStats['total_usuarios'] ?? 0),
                'organizaciones_activas' => (int)($rawStats['organizaciones_activas'] ?? $rawStats['total_organizaciones'] ?? 0),
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
    // 2. REGISTRAR COORDINADOR
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
    // 3. VER MI PERFIL
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'get_coordinador', methods: ['GET'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function getOne(int $id, Request $request, UsuarioRepository $userRepo, CoordinadorRepository $coordRepo): JsonResponse {
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
    // 4. ACTUALIZAR PERFIL
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'actualizar_coordinador', methods: ['PUT'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: CoordinadorUpdateDTO::class)))]
    public function actualizar(int $id, #[MapRequestPayload] CoordinadorUpdateDTO $dto, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse {
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
    // 6. GESTIÓN DE ESTADO DE USUARIOS
    // ========================================================================
    #[Route('/coord/{rol}/{id}/estado', name: 'coord_cambiar_estado_usuario', methods: ['PATCH'], requirements: ['rol' => 'voluntarios|organizaciones'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function cambiarEstadoUsuario(int $id, string $rol, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse {
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
    // 7. GESTIÓN ACTIVIDADES (LISTAR TODO + GESTIONAR)
    // ========================================================================
    #[Route('/coord/actividades', name: 'coord_listar_actividades', methods: ['GET'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function listarActividadesGlobal(Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $conn = $em->getConnection();
        // Consulta SQL para obtener todo (incluido borrados si estado es relevante)
        $sql = "
            SELECT 
                a.id_actividad, a.titulo, a.descripcion, a.fecha_inicio, a.duracion_horas, 
                a.cupo_maximo, a.ubicacion, a.estado_publicacion,
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

    #[Route('/coord/actividades/{id}/estado', name: 'coord_cambiar_estado_actividad', methods: ['PATCH'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function cambiarEstadoActividad(int $id, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $data = json_decode($request->getContent(), true);
        $actividad = $em->getRepository(Actividad::class)->find($id);
        if (!$actividad) return $this->json(['error' => 'Actividad no encontrada'], Response::HTTP_NOT_FOUND);

        $actividad->setEstadoPublicacion($data['estado']);
        $actividad->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json(['mensaje' => 'Estado actualizado'], Response::HTTP_OK);
    }

    #[Route('/coord/actividades/{id}', name: 'coord_borrar_actividad', methods: ['DELETE'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function borrarActividadCoord(int $id, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);
        
        $sql = 'EXEC SP_SoftDelete_Actividad @id_actividad = :id';
        $em->getConnection()->executeStatement($sql, ['id' => $id]);
        return $this->json(['mensaje' => 'Actividad eliminada'], Response::HTTP_OK);
    }

    #[Route('/coord/actividades/{id}', name: 'coord_editar_actividad', methods: ['PUT'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function editarActividadCoord(int $id, Request $request, ActividadRepository $repo, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse {
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

    #[Route('/coordinadores/{id}', name: 'borrar_usuario_coord', methods: ['DELETE'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function eliminar(int $id, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);
        
        $sql = 'EXEC SP_SoftDelete_Usuario @id_usuario = :id';
        $em->getConnection()->executeStatement($sql, ['id' => $id]);
        return $this->json(['mensaje' => 'Cuenta cerrada'], Response::HTTP_OK);
    }
}