<?php

namespace App\Controller;

use App\Entity\Coordinador;
use App\Entity\Usuario;
use App\Repository\RolRepository;
use App\Repository\UsuarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api', name: 'api_')]
#[OA\Tag(name: 'Coordinadores', description: 'Gestión interna y Dashboard')]
final class CoordinadorController extends AbstractController
{
    // ========================================================================
    // 1. DASHBOARD GLOBAL (Estadísticas vía SP)
    // ========================================================================
    #[Route('/admin/stats', name: 'admin_stats', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Métricas globales del sistema (Usando SP SQL)',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'titulo', type: 'string'),
                new OA\Property(property: 'metricas', type: 'object', example: ['voluntarios_activos' => 10, 'actividades_pendientes' => 2])
            ]
        )
    )]
    public function dashboard(EntityManagerInterface $em): JsonResponse
    {
        $conn = $em->getConnection();
        // Llamada al Procedimiento Almacenado optimizado
        $sql = 'EXEC SP_Dashboard_Stats';

        try {
            $stats = $conn->executeQuery($sql)->fetchAssociative();
            return $this->json([
                'titulo' => 'Panel de Control General',
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'metricas' => $stats
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error calculando estadísticas: ' . $e->getMessage()], 
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    // ========================================================================
    // 2. REGISTRAR COORDINADOR (POST)
    // ========================================================================
    #[Route('/coordinadores', name: 'registro_coordinador', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'nombre', type: 'string'),
                new OA\Property(property: 'correo', type: 'string'),
                new OA\Property(property: 'google_id', type: 'string')
            ]
        )
    )]
    public function registrar(
        Request $request,
        EntityManagerInterface $entityManager,
        RolRepository $rolRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['google_id'], $data['correo'], $data['nombre'])) {
            return $this->json(['error' => 'Faltan datos obligatorios'], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->beginTransaction();
        try {
            // A. USUARIO BASE
            $usuario = new Usuario();
            $usuario->setCorreo($data['correo']);
            $usuario->setGoogleId($data['google_id']);
            $usuario->setEstadoCuenta('Activa'); // Los jefes nacen activos

            $rolCoord = $rolRepository->findOneBy(['nombre' => 'Coordinador']);
            if (!$rolCoord) throw new \Exception("Error config: Rol 'Coordinador' no existe.");
            $usuario->setRol($rolCoord);

            $entityManager->persist($usuario);
            $entityManager->flush();

            // B. PERFIL COORDINADOR
            $coord = new Coordinador();
            $coord->setUsuario($usuario);
            $coord->setNombre($data['nombre']);
            $coord->setApellidos($data['apellidos'] ?? null);
            $coord->setTelefono($data['telefono'] ?? null);
            $coord->setUpdatedAt(new \DateTime());

            $entityManager->persist($coord);
            $entityManager->flush();
            
            $entityManager->commit();

            return $this->json([
                'mensaje' => 'Coordinador registrado correctamente',
                'perfil' => [
                    'id_usuario' => $usuario->getId(),
                    'nombre' => $coord->getNombre(),
                    'rol' => 'Coordinador'
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            $entityManager->rollback();
            return $this->json(['error' => 'Error al registrar: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ========================================================================
    // 3. VER MI PERFIL
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'get_coordinador', methods: ['GET'])]
    public function getOne(int $id, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        $usuario = $userRepo->find($id);

        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Coordinador no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $coord = $em->getRepository(Coordinador::class)->findOneBy(['usuario' => $usuario]);
        if (!$coord) {
            return $this->json(['error' => 'Perfil de datos incompleto'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($coord, Response::HTTP_OK, [], ['groups' => 'usuario:read']);
    }

    // ========================================================================
    // 4. ACTUALIZAR PERFIL
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'actualizar_coordinador', methods: ['PUT'])]
    public function actualizar(
        int $id,
        Request $request,
        UsuarioRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $usuario = $userRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);

        $coord = $em->getRepository(Coordinador::class)->findOneBy(['usuario' => $usuario]);
        if (!$coord) return $this->json(['error' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);

        $data = json_decode($request->getContent(), true);

        if (isset($data['nombre'])) $coord->setNombre($data['nombre']);
        if (isset($data['apellidos'])) $coord->setApellidos($data['apellidos']);
        if (isset($data['telefono'])) $coord->setTelefono($data['telefono']);
        
        $coord->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json($coord, Response::HTTP_OK, [], ['groups' => 'usuario:read']);
    }

    // ========================================================================
    // 5. ELIMINAR CUENTA (USANDO SP)
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'borrar_coordinador', methods: ['DELETE'])]
    public function eliminar(
        int $id,
        UsuarioRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $usuario = $userRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);

        // Usamos SP para consistencia
        $sql = 'EXEC SP_SoftDelete_Usuario @id_usuario = :id';
        try {
            $em->getConnection()->executeStatement($sql, ['id' => $id]);
            return $this->json(['mensaje' => 'Cuenta cerrada correctamente'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al cerrar cuenta'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}