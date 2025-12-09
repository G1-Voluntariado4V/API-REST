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
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class CoordinadorController extends AbstractController
{
    // ========================================================================
    // 1. DASHBOARD GLOBAL (Estadísticas)
    // ========================================================================

    #[Route('/admin/stats', name: 'admin_stats', methods: ['GET'])]
    public function dashboard(EntityManagerInterface $em): JsonResponse
    {

        // 1. Seguridad: Aquí deberías comprobar si el usuario es ROLE_COORDINADOR
        // $this->denyAccessUnlessGranted('ROLE_COORDINADOR'); (Lo activaremos luego)

        $conn = $em->getConnection();

        // 2. Llamamos al Procedimiento Almacenado de tu compañero
        $sql = 'EXEC SP_Dashboard_Stats';

        try {
            // fetchAssociative devuelve: { "voluntarios_activos": 10, "organizaciones_activas": 5 ... }
            $stats = $conn->executeQuery($sql)->fetchAssociative();

            return $this->json([
                'titulo' => 'Panel de Control General',
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'metricas' => $stats
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error calculando estadísticas: ' . $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // 2. REGISTRAR COORDINADOR (POST)
    // ========================================================================
    #[Route('/coordinadores', name: 'registro_coordinador', methods: ['POST'])]
    public function registrar(
        Request $request,
        EntityManagerInterface $entityManager,
        RolRepository $rolRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['google_id'], $data['correo'], $data['nombre'])) {
            return $this->json(['error' => 'Faltan datos obligatorios'], 400);
        }

        $entityManager->beginTransaction();
        try {
            // A. USUARIO
            $usuario = new Usuario();
            $usuario->setCorreo($data['correo']);
            $usuario->setGoogleId($data['google_id']);

            // Los Coordinadores nacen ACTIVOS (son los jefes)
            $usuario->setEstadoCuenta('Activa');

            // Buscar Rol (ID 4 o por Nombre)
            $rolCoord = $rolRepository->findOneBy(['nombre' => 'Coordinador']);
            if (!$rolCoord) throw new \Exception("El rol 'Coordinador' no existe en la BBDD.");

            $usuario->setRol($rolCoord);

            $entityManager->persist($usuario);
            $entityManager->flush();

            // B. PERFIL
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
            ], 201);
        } catch (\Exception $e) {
            $entityManager->rollback();
            return $this->json(['error' => 'Error al registrar: ' . $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // 3. VER MI PERFIL (GET ONE)
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'get_coordinador', methods: ['GET'])]
    public function getOne(int $id, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        $usuario = $userRepo->find($id);

        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Coordinador no encontrado'], 404);
        }

        // Buscamos el perfil específico
        $coord = $em->getRepository(Coordinador::class)->findOneBy(['usuario' => $usuario]);

        if (!$coord) {
            return $this->json(['error' => 'Perfil de datos incompleto'], 404);
        }

        // Devolvemos el perfil completo
        return $this->json($coord, 200, [], ['groups' => 'usuario:read']);
    }

    // ========================================================================
    // 4. ACTUALIZAR PERFIL (PUT)
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'actualizar_coordinador', methods: ['PUT'])]
    public function actualizar(
        int $id,
        Request $request,
        UsuarioRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $usuario = $userRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], 404);

        $coord = $em->getRepository(Coordinador::class)->findOneBy(['usuario' => $usuario]);
        if (!$coord) return $this->json(['error' => 'Perfil no encontrado'], 404);

        $data = json_decode($request->getContent(), true);

        if (isset($data['nombre'])) $coord->setNombre($data['nombre']);
        if (isset($data['apellidos'])) $coord->setApellidos($data['apellidos']);
        if (isset($data['telefono'])) $coord->setTelefono($data['telefono']);

        $coord->setUpdatedAt(new \DateTime());

        $em->flush();

        return $this->json($coord, 200, [], ['groups' => 'usuario:read']);
    }

    // ========================================================================
    // 5. ELIMINAR CUENTA PROPIA (DELETE)
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'borrar_coordinador', methods: ['DELETE'])]
    public function eliminar(
        int $id,
        UsuarioRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $usuario = $userRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], 404);

        // Soft Delete manual
        $usuario->setDeletedAt(new \DateTimeImmutable());
        $usuario->setEstadoCuenta('Bloqueada');

        $em->flush();

        return $this->json(['mensaje' => 'Cuenta de coordinador cerrada correctamente'], 200);
    }
}
