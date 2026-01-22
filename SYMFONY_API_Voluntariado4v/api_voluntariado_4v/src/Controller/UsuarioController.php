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
    // 1. LISTAR USUARIOS (GET)
    // ========================================================================
    #[Route('/usuarios', name: 'listar_usuarios', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Listado de usuarios registrados (Admin)',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(property: 'id_usuario', type: 'integer', example: 10),
                new OA\Property(property: 'correo', type: 'string', example: 'usuario@test.com'),
                new OA\Property(property: 'nombre_rol', type: 'string', example: 'Voluntario'),
                new OA\Property(property: 'estado_cuenta', type: 'string', example: 'Activa'),
                new OA\Property(property: 'img_perfil', type: 'string', nullable: true)
            ])
        )
    )]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $conn = $em->getConnection();
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
            try {
                $sqlFallback = "SELECT id_usuario, correo, 'Voluntario' as nombre_rol, estado_cuenta, NULL as img_perfil FROM USUARIO WHERE deleted_at IS NULL";
                $usuarios = $conn->executeQuery($sqlFallback)->fetchAllAssociative();
                return $this->json($usuarios, Response::HTTP_OK);
            } catch (\Exception $ex) {
                return $this->json(['error' => 'Error al listar usuarios: ' . $e->getMessage()], 500);
            }
        }
    }

    // ========================================================================
    // 2. CREAR USUARIO (POST)
    // ========================================================================
    #[Route('/usuarios', name: 'crear_usuario', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'correo', type: 'string', example: 'nuevo@test.com'),
            new OA\Property(property: 'google_id', type: 'string', example: 'g_12345'),
            new OA\Property(property: 'id_rol', type: 'integer', example: 2)
        ])
    )]
    #[OA\Response(response: 201, description: 'Usuario creado (pendiente de completar perfil)')]
    #[OA\Response(response: 409, description: 'Correo/GoogleID duplicado')]
    public function crear(Request $request, EntityManagerInterface $entityManager, RolRepository $rolRepository): JsonResponse
    {
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

    // ========================================================================
    // 3. ELIMINAR USUARIO (DELETE)
    // ========================================================================
    #[Route('/usuarios/{id}', name: 'borrar_usuario', methods: ['DELETE'])]
    #[OA\Response(response: 200, description: 'Usuario eliminado (Soft Delete)')]
    public function delete(int $id, UsuarioRepository $repo, EntityManagerInterface $em): JsonResponse
    {
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

    // ========================================================================
    // 4. SUBIR IMAGEN DE PERFIL (POST)
    // ========================================================================
    #[Route('/usuarios/{id}/imagen', name: 'upload_imagen_usuario', methods: ['POST'])]
    #[OA\RequestBody(
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(properties: [
                new OA\Property(property: 'imagen', type: 'string', format: 'binary')
            ])
        )
    )]
    #[OA\Response(response: 200, description: 'Imagen de perfil actualizada')]
    public function uploadImagen(int $id, Request $request, UsuarioRepository $usuarioRepo, EntityManagerInterface $em, #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%uploads_directory%')] string $uploadsDirectory): JsonResponse
    {
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

    // ========================================================================
    // 5. CAMBIAR ROL DE USUARIO (PUT)
    // ========================================================================
    #[Route('/usuarios/{id}/rol', name: 'cambiar_rol_usuario', methods: ['PUT'])]
    #[IsGranted('ROLE_COORDINADOR')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'id_rol', type: 'integer', example: 3)
        ])
    )]
    #[OA\Response(response: 200, description: 'Rol actualizado')]
    public function cambiarRol(int $id, Request $request, UsuarioRepository $usuarioRepo, RolRepository $rolRepo, EntityManagerInterface $em): JsonResponse
    {
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
