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
        if ($user && in_array($user->getRol()->getNombre(), ['Coordinador', 'Administrador'])) {
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
        // Nota: Asegúrate de que el SP 'SP_Dashboard_Stats' existe en tu BBDD, si no, usa el código SQL directo que te di antes.
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
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', description: 'ID de Coordinador (Solo otro coordinador puede crear)', required: true)]
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
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', description: 'ID de Coordinador', required: true)]
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
    // 4. ACTUALIZAR PERFIL (PUT) - AHORA CON DTO
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'actualizar_coordinador', methods: ['PUT'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', description: 'ID de Coordinador', required: true)]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: new Model(type: CoordinadorUpdateDTO::class) // <--- Documentación automática
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

        // Usamos el operador null coalescing (??) por si envían null, o permitimos borrarlo
        // Si quieres que null signifique "no cambiar", tendrías que hacer if ($dto->apellidos !== null)...
        // Pero en un PUT se suele reemplazar todo el recurso, así que asignamos directo.
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
    // 5. MODERACIÓN: ACTIVIDADES (PATCH)
    // ========================================================================
    #[Route('/coord/actividades/{id}/estado', name: 'coord_cambiar_estado_actividad', methods: ['PATCH'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true)]
    #[OA\RequestBody(content: new OA\JsonContent(properties: [new OA\Property(property: 'estado', type: 'string', enum: ['Publicada', 'Rechazada'])]))]
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

        $actividad = $em->getRepository(Actividad::class)->find($id);
        if (!$actividad) return $this->json(['error' => 'Actividad no encontrada'], 404);

        $actividad->setEstadoPublicacion($nuevoEstado);
        $em->flush();

        return $this->json(['mensaje' => "Actividad actualizada a: $nuevoEstado"], 200);
    }
}
