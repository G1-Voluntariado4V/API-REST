<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Repository\RolRepository;
use App\Repository\UsuarioRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('', name: 'api_')]
#[OA\Tag(name: 'Usuarios', description: 'Gestión administrativa de usuarios')]
final class UsuarioController extends AbstractController
{
    // ========================================================================
    // 1. LISTAR USUARIOS (GET) - JSON KEY FIX
    // ========================================================================
    #[Route('/usuarios', name: 'listar_usuarios', methods: ['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $conn = $em->getConnection();
        // Usamos alias explícitos para que coincidan con UserResponse.java
        // Aunque la vista cambie nombres de columnas, el alias 'nombre_rol' asegura compatibilidad
        $sql = "
            SELECT 
                id_usuario, 
                correo, 
                nombre_rol, 
                estado_cuenta,
                img_perfil 
            FROM VW_Usuarios_Activos
        ";

        try {
            $usuarios = $conn->executeQuery($sql)->fetchAllAssociative();
            return $this->json($usuarios, Response::HTTP_OK);
        } catch (\Exception $e) {
            // Fallback si la vista no tiene img_perfil o falla algo
            try {
                $sqlFallback = "SELECT id_usuario, correo, 'Voluntario' as nombre_rol, estado_cuenta, NULL as img_perfil FROM USUARIO WHERE deleted_at IS NULL";
                $usuarios = $conn->executeQuery($sqlFallback)->fetchAllAssociative();
                return $this->json($usuarios, Response::HTTP_OK);
            } catch (\Exception $ex) {
                return $this->json(['error' => 'Error al listar usuarios: ' . $e->getMessage()], 500);
            }
        }
    }

    // ... (El resto de métodos CREAR, BORRAR, IMAGEN, ROL se mantienen igual) ...
    // Incluyo la clase completa para copiar y pegar:

    #[Route('/usuarios', name: 'crear_usuario', methods: ['POST'])]
    public function crear(Request $request, EntityManagerInterface $entityManager, RolRepository $rolRepository): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (empty($data['correo']) || empty($data['google_id']) || empty($data['id_rol'])) {
            return $this->json(['error' => 'Faltan datos'], 400);
        }
        $rol = $rolRepository->find($data['id_rol']);
        if (!$rol) return $this->json(['error' => 'Rol no encontrado'], 404);

        $usuario = new Usuario();
        $usuario->setCorreo($data['correo']);
        $usuario->setGoogleId($data['google_id']);
        $usuario->setRol($rol);
        $usuario->setEstadoCuenta('Pendiente');

        try {
            $entityManager->persist($usuario);
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            return $this->json(['error' => 'Correo/GoogleID ya existe'], 409);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
        return $this->json($usuario, 201, [], ['groups' => 'usuario:read']);
    }

    #[Route('/usuarios/{id}', name: 'borrar_usuario', methods: ['DELETE'])]
    public function delete(int $id, UsuarioRepository $repo, EntityManagerInterface $em): JsonResponse {
        $usuario = $repo->find($id);
        if (!$usuario) return $this->json(['error' => 'No encontrado'], 404);

        try {
            $sql = 'EXEC SP_SoftDelete_Usuario @id_usuario = :id';
            $em->getConnection()->executeStatement($sql, ['id' => $id]);
            return $this->json(['mensaje' => 'Usuario eliminado'], 200);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/usuarios/{id}/imagen', name: 'upload_imagen_usuario', methods: ['POST'])]
    public function uploadImagen(int $id, Request $request, UsuarioRepository $usuarioRepo, EntityManagerInterface $em, #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%uploads_directory%')] string $uploadsDirectory): JsonResponse {
        $usuario = $usuarioRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], 404);

        $file = $request->files->get('imagen');
        if (!$file) return $this->json(['error' => 'No hay archivo'], 400);

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) return $this->json(['error' => 'Formato incorrecto'], 400);

        $targetDirectory = $uploadsDirectory . '/usuarios';
        if (!is_dir($targetDirectory)) mkdir($targetDirectory, 0777, true);

        $filename = uniqid('usr_' . $id . '_') . '.' . $extension;
        $file->move($targetDirectory, $filename);

        if ($usuario->getImgPerfil()) @unlink($uploadsDirectory . '/usuarios/' . $usuario->getImgPerfil());

        $usuario->setImgPerfil($filename);
        $em->persist($usuario);
        $em->flush();

        return $this->json(['mensaje' => 'Foto actualizada', 'img_perfil' => $filename], 200);
    }

    #[Route('/usuarios/{id}/rol', name: 'cambiar_rol_usuario', methods: ['PUT'])]
    #[IsGranted('ROLE_COORDINADOR')]
    public function cambiarRol(int $id, Request $request, UsuarioRepository $usuarioRepo, RolRepository $rolRepo, EntityManagerInterface $em): JsonResponse {
        $usuario = $usuarioRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], 404);

        $data = json_decode($request->getContent(), true);
        if (!isset($data['id_rol'])) return $this->json(['error' => 'Falta id_rol'], 400);

        $nuevoRol = $rolRepo->find((int)$data['id_rol']);
        if (!$nuevoRol) return $this->json(['error' => 'Rol no encontrado'], 404);

        $usuario->setRol($nuevoRol);
        $em->persist($usuario);
        $em->flush();

        return $this->json(['mensaje' => 'Rol actualizado', 'usuario' => ['rol' => $nuevoRol->getNombre()]], 200);
    }
}