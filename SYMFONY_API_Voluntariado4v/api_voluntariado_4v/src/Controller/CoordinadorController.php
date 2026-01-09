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
    // ========================================================================
    // HELPER: Simulación de Seguridad (X-Admin-Id)
    // ========================================================================
    private function checkCoordinador(Request $request, UsuarioRepository $repo): ?Usuario
    {
        $adminId = $request->headers->get('X-Admin-Id');
        if (!$adminId) return null;

        $user = $repo->find($adminId);
        // Validamos que exista y sea Coordinador o Admin
        if ($user && in_array($user->getRol()->getNombre(), ['Coordinador'])) {
            return $user;
        }
        return null;
    }

    // ========================================================================
    // 1. DASHBOARD GLOBAL (Estadísticas vía SP)
    // ========================================================================
    #[Route('/coord/stats', name: 'coord_stats', methods: ['GET'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', description: 'ID de Coordinador', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Métricas globales del sistema',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'titulo', type: 'string'),
            new OA\Property(property: 'metricas', type: 'object')
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
        // Llamada al Procedimiento Almacenado
        try {
            // Opción A: Si tienes el SP creado
            $stats = $conn->executeQuery('EXEC SP_Dashboard_Stats')->fetchAssociative();

            // Opción B (Fallback si no tienes el SP aún): SQL directo
            /*
            $stats = [
                'total_usuarios' => $conn->fetchOne('SELECT COUNT(*) FROM USUARIO WHERE deleted_at IS NULL'),
                'actividades_publicadas' => $conn->fetchOne("SELECT COUNT(*) FROM ACTIVIDAD WHERE estado_publicacion = 'Publicada'"),
                'inscripciones_pendientes' => $conn->fetchOne("SELECT COUNT(*) FROM INSCRIPCION WHERE estado_solicitud = 'Pendiente'")
            ];
            */

            return $this->json([
                'titulo' => 'Panel de Control General',
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'metricas' => $stats
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error calculando estadísticas: ' . $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // 2. REGISTRAR COORDINADOR (POST)
    // ========================================================================
    #[Route('/coordinadores', name: 'registro_coordinador', methods: ['POST'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', description: 'ID de Coordinador (Solo otro coordinador puede crear)', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: CoordinadorCreateDTO::class)))]
    #[OA\Response(response: 201, description: 'Coordinador creado', content: new OA\JsonContent(ref: new Model(type: CoordinadorResponseDTO::class)))]
    public function registrar(
        Request $request,
        #[MapRequestPayload] CoordinadorCreateDTO $dto,
        EntityManagerInterface $entityManager,
        RolRepository $rolRepository,
        UsuarioRepository $userRepo
    ): JsonResponse {

        // Seguridad: Solo un coordinador puede crear a otro
        if (!$this->checkCoordinador($request, $userRepo)) {
            return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);
        }

        $entityManager->beginTransaction();
        try {
            // A. USUARIO BASE
            $usuario = new Usuario();
            $usuario->setCorreo($dto->correo);
            $usuario->setGoogleId($dto->google_id);
            $usuario->setEstadoCuenta('Activa');

            $rolCoord = $rolRepository->findOneBy(['nombre' => 'Coordinador']);
            if (!$rolCoord) throw new \Exception("Rol 'Coordinador' no configurado.");
            $usuario->setRol($rolCoord);

            $entityManager->persist($usuario);
            $entityManager->flush();

            // B. PERFIL COORDINADOR
            $coord = new Coordinador();
            $coord->setUsuario($usuario);
            $coord->setNombre($dto->nombre);
            $coord->setApellidos($dto->apellidos);
            $coord->setTelefono($dto->telefono);

            $entityManager->persist($coord);
            $entityManager->flush();
            $entityManager->commit();

            return $this->json(
                CoordinadorResponseDTO::fromEntity($coord),
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            $entityManager->rollback();
            return $this->json(['error' => 'Error al registrar: ' . $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // 3. VER MI PERFIL (GET)
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'get_coordinador', methods: ['GET'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', description: 'ID de Coordinador', required: true, schema: new OA\Schema(type: 'integer'))]
    public function getOne(
        int $id,
        Request $request,
        UsuarioRepository $userRepo,
        CoordinadorRepository $coordRepo
    ): JsonResponse {

        // Seguridad: El ID solicitado debe coincidir con el header (Ver tu propio perfil)
        // O permitir si es otro coordinador (depende de tu regla de negocio). Asumimos que entre ellos se pueden ver.
        if (!$this->checkCoordinador($request, $userRepo)) {
            return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);
        }

        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Coordinador no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $coord = $coordRepo->findOneBy(['usuario' => $usuario]);
        if (!$coord) {
            return $this->json(['error' => 'Perfil incompleto'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(CoordinadorResponseDTO::fromEntity($coord), Response::HTTP_OK);
    }

    // ========================================================================
    // 4. ACTUALIZAR PERFIL (PUT) - CON DTO
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'actualizar_coordinador', methods: ['PUT'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', description: 'ID de Coordinador', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: new Model(type: CoordinadorUpdateDTO::class)
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Perfil actualizado',
        content: new OA\JsonContent(
            ref: new Model(type: CoordinadorResponseDTO::class)
        )
    )]
    public function actualizar(
        int $id,
        #[MapRequestPayload] CoordinadorUpdateDTO $dto, // <--- Validación automática
        Request $request,
        UsuarioRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {

        // 1. Seguridad
        if (!$this->checkCoordinador($request, $userRepo)) {
            return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);
        }

        // 2. Buscar Usuario y Perfil
        $usuario = $userRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);

        $coord = $em->getRepository(Coordinador::class)->findOneBy(['usuario' => $usuario]);
        if (!$coord) return $this->json(['error' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);

        // 3. Actualizar datos desde el DTO
        $coord->setNombre($dto->nombre);
        $coord->setApellidos($dto->apellidos);
        $coord->setTelefono($dto->telefono);

        $coord->setUpdatedAt(new \DateTime());

        $em->flush();

        // 4. Devolver DTO de respuesta
        return $this->json(
            CoordinadorResponseDTO::fromEntity($coord),
            Response::HTTP_OK
        );
    }

    // ========================================================================
    // 6. MODERACIÓN: GESTIÓN DE ESTADO DE USUARIOS (Voluntarios/Organizaciones)
    // ========================================================================
    #[Route('/coord/{rol}/{id}/estado', name: 'coord_cambiar_estado_usuario', methods: ['PATCH'], requirements: ['rol' => 'voluntarios|organizaciones'])]
    #[OA\Response(response: 200, description: 'Estado de usuario actualizado.')]
    #[OA\Response(response: 400, description: 'Estado o Rol inválido.')]
    #[OA\Parameter(name: 'rol', description: 'Tipo de usuario (voluntarios o organizaciones)', in: 'path', schema: new OA\Schema(type: 'string', enum: ['voluntarios', 'organizaciones']))]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'estado', type: 'string', description: 'Nuevo estado', enum: ['Activa', 'Rechazada', 'Bloqueada'])
            ]
        )
    )]
    public function cambiarEstadoUsuario(
        int $id,
        string $rol,
        Request $request,
        UsuarioRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {

        if (!$this->checkCoordinador($request, $userRepo)) {
            return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $nuevoEstado = $data['estado'] ?? null;
        $estadosValidos = ['Activa', 'Rechazada', 'Bloqueada'];

        // 1. Validar Rol y Estado
        if (!in_array($rol, ['voluntarios', 'organizaciones'])) {
            return $this->json(['error' => 'Rol de usuario inválido para esta acción.'], Response::HTTP_BAD_REQUEST);
        }
        if (!in_array($nuevoEstado, $estadosValidos)) {
            return $this->json(['error' => 'Estado de cuenta inválido. Use: ' . implode(', ', $estadosValidos)], Response::HTTP_BAD_REQUEST);
        }

        // 2. Buscar y Actualizar
        $usuario = $userRepo->find($id);
        if (!$usuario) {
            return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $usuario->setEstadoCuenta($nuevoEstado);
        $usuario->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json(['mensaje' => 'Estado de la cuenta actualizado a ' . $nuevoEstado], Response::HTTP_OK);
    }

    // ========================================================================
    // 7. GESTIÓN TOTAL DE ACTIVIDADES (COORDINACIÓN)
    // ========================================================================

    // 7.1 MODERAR ESTADO (Publicar/Rechazar)
    #[Route('/coord/actividades/{id}/estado', name: 'coord_cambiar_estado_actividad', methods: ['PATCH'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Estado de publicación actualizado.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'estado', type: 'string', enum: ['Publicada', 'En revision', 'Cancelada'])]))]
    public function cambiarEstadoActividad(
        int $id,
        Request $request,
        UsuarioRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        if (!$this->checkCoordinador($request, $userRepo)) {
            return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $nuevoEstado = $data['estado'] ?? null;
        $estadosValidos = ['Publicada', 'En revision', 'Cancelada', 'Rechazada'];

        if (!in_array($nuevoEstado, $estadosValidos)) {
            return $this->json(['error' => 'Estado de publicación inválido. Use: ' . implode(', ', $estadosValidos)], Response::HTTP_BAD_REQUEST);
        }

        $actividad = $em->getRepository(Actividad::class)->find($id);
        if (!$actividad) return $this->json(['error' => 'Actividad no encontrada'], Response::HTTP_NOT_FOUND);

        $actividad->setEstadoPublicacion($nuevoEstado);
        $actividad->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json(['mensaje' => 'Estado de publicación actualizado a ' . $nuevoEstado], Response::HTTP_OK);
    }

    // 7.2 BORRAR ACTIVIDAD (COORD DELETE) - Borrado forzoso
    #[Route('/coord/actividades/{id}', name: 'coord_borrar_actividad', methods: ['DELETE'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Actividad eliminada por coordinación (Soft Delete)')]
    public function borrarActividadCoord(
        int $id,
        Request $request,
        UsuarioRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        if (!$this->checkCoordinador($request, $userRepo)) {
            return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);
        }

        // Reutilizamos el SP de Soft Delete para consistencia
        $sql = 'EXEC SP_SoftDelete_Actividad @id_actividad = :id';
        try {
            $em->getConnection()->executeStatement($sql, ['id' => $id]);
            return $this->json(['mensaje' => 'Actividad eliminada por Coordinador'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al eliminar la actividad'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // 7.3 MODIFICAR ACTIVIDAD (COORD UPDATE) - Edición de contenido
    #[Route('/coord/actividades/{id}', name: 'coord_editar_actividad', methods: ['PUT'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(description: 'Datos a forzar actualización', content: new OA\JsonContent(type: 'object'))]
    public function editarActividadCoord(
        int $id,
        Request $request,
        ActividadRepository $repo,
        UsuarioRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        // Esta función permite al coordinador corregir textos o datos de cualquier actividad
        if (!$this->checkCoordinador($request, $userRepo)) {
            return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);
        }

        $actividad = $repo->find($id);
        if (!$actividad) return $this->json(['error' => 'Actividad no encontrada'], Response::HTTP_NOT_FOUND);

        // Aquí usamos json_decode para permitir edición parcial rápida ("Hotfix")
        $data = json_decode($request->getContent(), true);

        // Actualización simple de campos escalares
        if (isset($data['titulo'])) $actividad->setTitulo($data['titulo']);
        if (isset($data['descripcion'])) $actividad->setDescripcion($data['descripcion']);
        if (isset($data['cupo_maximo'])) $actividad->setCupoMaximo($data['cupo_maximo']);
        if (isset($data['estado'])) $actividad->setEstadoPublicacion($data['estado']);

        $actividad->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json(['mensaje' => 'Actividad editada por Coordinación', 'titulo_nuevo' => $actividad->getTitulo()], Response::HTTP_OK);
    }

    // ========================================================================
    // 8. ELIMINAR CUENTA (Coordinador borrando usuarios o a sí mismo)
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'borrar_usuario_coord', methods: ['DELETE'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function eliminar(
        int $id,
        Request $request,
        UsuarioRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {

        if (!$this->checkCoordinador($request, $userRepo)) {
            return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);
        }

        $usuario = $userRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);

        // Usamos SP para consistencia
        $sql = 'EXEC SP_SoftDelete_Usuario @id_usuario = :id';
        try {
            $em->getConnection()->executeStatement($sql, ['id' => $id]);
            return $this->json(['mensaje' => 'Cuenta cerrada correctamente'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al cerrar la cuenta'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
