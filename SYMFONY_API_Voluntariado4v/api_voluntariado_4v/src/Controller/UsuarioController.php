<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Repository\RolRepository;
use App\Repository\UsuarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class UsuarioController extends AbstractController
{
    // ========================================================================
    // 1. LISTAR USUARIOS (GET) -> Usando Vista SQL
    // ========================================================================
    #[Route('/usuarios', name: 'usuarios_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        // Usamos la vista SQL para obtener solo usuarios activos y con el nombre del rol ya unido
        $conn = $em->getConnection();
        $sql = 'SELECT * FROM VW_Usuarios_Activos';

        try {
            $usuarios = $conn->executeQuery($sql)->fetchAllAssociative();
            return $this->json($usuarios);
        } catch (\Exception $e) {
            // Si la vista no existe (aún no has migrado), fallback a Doctrine
            // Esto es útil mientras desarrollas
            return $this->json(['error' => 'Error al listar usuarios (Vista no encontrada)'], 500);
        }
    }

    // ========================================================================
    // 2. CREAR USUARIO BASE (POST) - Solo para admin/pruebas
    // Nota: Normalmente se usa /auth/register en VoluntarioController, 
    // pero este sirve para crear admins u organizaciones a mano.
    // ========================================================================
    #[Route('/usuarios', name: 'crear_usuario', methods: ['POST'])]
    public function crear(
        Request $request,
        EntityManagerInterface $entityManager,
        RolRepository $rolRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // 1. Validaciones
        if (!isset($data['correo'], $data['google_id'], $data['id_rol'])) {
            return $this->json(['error' => 'Faltan datos (correo, google_id, id_rol)'], 400);
        }

        // 2. Buscar Rol
        $rol = $rolRepository->find($data['id_rol']);
        if (!$rol) {
            return $this->json(['error' => 'Rol no encontrado'], 404);
        }

        // 3. Crear Usuario (Sin password)
        $usuario = new Usuario();
        $usuario->setCorreo($data['correo']);
        $usuario->setGoogleId($data['google_id']);
        $usuario->setRol($rol);
        $usuario->setEstadoCuenta('Pendiente'); // Valor por defecto seguro

        try {
            $entityManager->persist($usuario);
            $entityManager->flush();
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al guardar: ' . $e->getMessage()], 500);
        }

        return $this->json($usuario, 201, [], ['groups' => 'usuario:read']);
    }

    // ========================================================================
    // 3. ELIMINAR USUARIO (DELETE) - Soft Delete Manual (o via SP)
    // ========================================================================
    #[Route('/usuarios/{id}', name: 'borrar_usuario', methods: ['DELETE'])]
    public function delete(
        int $id,
        UsuarioRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        $usuario = $repo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], 404);

        // Opción A: Usar el SP de SQL Server (Más robusto)
        /*
        $sql = 'EXEC SP_SoftDelete_Usuario @id_usuario = :id';
        $em->getConnection()->executeStatement($sql, ['id' => $id]);
        */

        // Opción B: Usar Doctrine (Más portable ahora mismo)
        $usuario->setDeletedAt(new \DateTimeImmutable());
        $usuario->setEstadoCuenta('Bloqueada');

        $em->flush();

        return $this->json(['mensaje' => 'Usuario eliminado (Soft Delete)'], 200);
    }
}
