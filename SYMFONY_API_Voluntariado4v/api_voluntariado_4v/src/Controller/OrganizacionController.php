<?php

namespace App\Controller;

use App\Entity\Organizacion;
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
#[OA\Tag(name: 'Organizaciones', description: 'Gestión de ONGs y entidades')]
final class OrganizacionController extends AbstractController
{
    // ========================================================================
    // 1. LISTAR (GET) - Vista SQL
    // ========================================================================
    #[Route('/organizaciones', name: 'listar_organizaciones', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Listado de organizaciones activas (Vista SQL)',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'object'))
    )]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $conn = $em->getConnection();
        $sql = 'SELECT * FROM VW_Organizaciones_Activas';
        try {
            $listado = $conn->executeQuery($sql)->fetchAllAssociative();
            return $this->json($listado, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al cargar organizaciones'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ========================================================================
    // 2. REGISTRAR (POST)
    // ========================================================================
    #[Route('/organizaciones', name: 'registro_organizacion', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'google_id', type: 'string'),
                new OA\Property(property: 'correo', type: 'string'),
                new OA\Property(property: 'nombre', type: 'string', description: 'Nombre de la ONG'),
                new OA\Property(property: 'cif', type: 'string', description: 'Identificador fiscal')
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Organización registrada (Pendiente de validación)')]
    public function registrar(
        Request $request,
        EntityManagerInterface $entityManager,
        RolRepository $rolRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['google_id'], $data['correo'], $data['nombre'], $data['cif'])) {
            return $this->json(['error' => 'Faltan datos obligatorios'], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->beginTransaction();

        try {
            // A. USUARIO BASE
            $usuario = new Usuario();
            $usuario->setCorreo($data['correo']);
            $usuario->setGoogleId($data['google_id']);
            $usuario->setEstadoCuenta('Pendiente'); // Nace pendiente de validación por Admin

            $rolOrg = $rolRepository->findOneBy(['nombre' => 'Organizacion']);
            if (!$rolOrg) throw new \Exception("Rol 'Organizacion' no encontrado");
            $usuario->setRol($rolOrg);

            $entityManager->persist($usuario);
            $entityManager->flush();

            // B. PERFIL ORGANIZACIÓN
            $org = new Organizacion();
            $org->setUsuario($usuario);
            $org->setNombre($data['nombre']);
            $org->setCif($data['cif']);
            $org->setDescripcion($data['descripcion'] ?? null);
            $org->setDireccion($data['direccion'] ?? null);
            $org->setSitioWeb($data['sitio_web'] ?? null);
            $org->setTelefono($data['telefono'] ?? null);
            $org->setImgPerfil($data['img_perfil'] ?? null);
            $org->setUpdatedAt(new \DateTime()); // Usamos DateTime de PHP para asegurar compatibilidad

            $entityManager->persist($org);
            $entityManager->flush();

            $entityManager->commit();

            return $this->json($org, Response::HTTP_CREATED, [], ['groups' => 'usuario:read']);
        } catch (\Exception $e) {
            $entityManager->rollback();
            return $this->json(['error' => 'Error al registrar: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ========================================================================
    // 3. VALIDAR CUENTA (PATCH) - Acción de Admin
    // ========================================================================
    #[Route('/organizaciones/{id}/validar', name: 'validar_organizacion', methods: ['PATCH'])]
    #[OA\RequestBody(
        description: 'Nuevo estado de la cuenta',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'estado', type: 'string', enum: ['Activa', 'Rechazada', 'Bloqueada'], example: 'Activa')
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Estado actualizado')]
    public function validar(int $id, UsuarioRepository $userRepo, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $usuario = $userRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);

        $data = json_decode($request->getContent(), true);
        $nuevoEstado = $data['estado'] ?? 'Activa';

        $usuario->setEstadoCuenta($nuevoEstado);
        $usuario->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json(['mensaje' => "Estado actualizado a: $nuevoEstado"], Response::HTTP_OK);
    }

    // ========================================================================
    // 4. GET ONE
    // ========================================================================
    #[Route('/organizaciones/{id}', name: 'get_organizacion', methods: ['GET'])]
    public function getOne(int $id, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        $usuario = $userRepo->find($id);
        // Si no existe o tiene deleted_at (Soft Deleted)
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'No encontrada'], Response::HTTP_NOT_FOUND);
        }

        // Buscamos el perfil específico
        $org = $em->getRepository(Organizacion::class)->findOneBy(['usuario' => $usuario]);
        if (!$org) return $this->json(['error' => 'Perfil incompleto'], Response::HTTP_NOT_FOUND);

        return $this->json($org, Response::HTTP_OK, [], ['groups' => 'usuario:read']);
    }

    // ========================================================================
    // 5. ACTUALIZAR (PUT)
    // ========================================================================
    #[Route('/organizaciones/{id}', name: 'actualizar_organizacion', methods: ['PUT'])]
    public function actualizar(int $id, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        $usuario = $userRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);

        $org = $em->getRepository(Organizacion::class)->findOneBy(['usuario' => $usuario]);
        if (!$org) return $this->json(['error' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);

        $data = json_decode($request->getContent(), true);

        if (isset($data['nombre'])) $org->setNombre($data['nombre']);
        if (isset($data['descripcion'])) $org->setDescripcion($data['descripcion']);
        if (isset($data['direccion'])) $org->setDireccion($data['direccion']);
        if (isset($data['sitio_web'])) $org->setSitioWeb($data['sitio_web']);
        if (isset($data['telefono'])) $org->setTelefono($data['telefono']);
        if (isset($data['img_perfil'])) $org->setImgPerfil($data['img_perfil']);

        $org->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json($org, Response::HTTP_OK, [], ['groups' => 'usuario:read']);
    }

    // ========================================================================
    // 6. ELIMINAR (DELETE) - Usando SP SoftDelete
    // ========================================================================
    #[Route('/organizaciones/{id}', name: 'borrar_organizacion', methods: ['DELETE'])]
    public function eliminar(int $id, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        $usuario = $userRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);

        // Usamos el SP para asegurar borrado lógico y bloqueo de cuenta
        $sql = 'EXEC SP_SoftDelete_Usuario @id_usuario = :id';
        try {
            $em->getConnection()->executeStatement($sql, ['id' => $id]);
            return $this->json(['mensaje' => 'Organización eliminada correctamente (Soft Delete)'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al eliminar organización'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
