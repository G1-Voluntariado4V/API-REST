<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Repository\RolRepository;
use App\Repository\UsuarioRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException; // Para capturar duplicados
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response; // Códigos HTTP (200, 201, 400...)
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA; // Documentación

#[Route('', name: 'api_')]
#[OA\Tag(name: 'Usuarios', description: 'Gestión administrativa de usuarios')]
final class UsuarioController extends AbstractController
{
    // ========================================================================
    // 1. LISTAR USUARIOS (GET) -> Usando Vista SQL
    // ========================================================================
    #[Route('/usuarios', name: 'usuarios_index', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Listado de usuarios activos (Desde Vista SQL)',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(property: 'id_usuario', type: 'integer'),
                new OA\Property(property: 'correo', type: 'string'),
                new OA\Property(property: 'nombre_rol', type: 'string'),
                new OA\Property(property: 'estado_cuenta', type: 'string')
            ])
        )
    )]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        // Usamos la vista SQL definida en tu script BBDD
        $conn = $em->getConnection();
        $sql = 'SELECT * FROM VW_Usuarios_Activos';

        try {
            $usuarios = $conn->executeQuery($sql)->fetchAllAssociative();
            return $this->json($usuarios, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al listar usuarios: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    // ========================================================================
    // 2. CREAR USUARIO BASE (POST)
    // ========================================================================
    #[Route('/usuarios', name: 'crear_usuario', methods: ['POST'])]
    #[OA\RequestBody(
        description: 'Datos básicos del usuario',
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'correo', type: 'string', example: 'usuario@test.com'),
                new OA\Property(property: 'google_id', type: 'string', example: 'goog_123abc'),
                new OA\Property(property: 'id_rol', type: 'integer', example: 2)
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Usuario creado con éxito')]
    #[OA\Response(response: 409, description: 'Conflicto: El correo o GoogleID ya existen')]
    #[OA\Response(response: 404, description: 'Rol no encontrado')]
    public function crear(
        Request $request,
        EntityManagerInterface $entityManager,
        RolRepository $rolRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // 1. Validaciones
        if (empty($data['correo']) || empty($data['google_id']) || empty($data['id_rol'])) {
            return $this->json(
                ['error' => 'Faltan datos obligatorios (correo, google_id, id_rol)'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // 2. Buscar Rol
        $rol = $rolRepository->find($data['id_rol']);
        if (!$rol) {
            return $this->json(['error' => 'Rol no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // 3. Crear Usuario
        $usuario = new Usuario();
        $usuario->setCorreo($data['correo']);
        $usuario->setGoogleId($data['google_id']);
        $usuario->setRol($rol);
        $usuario->setEstadoCuenta('Pendiente');
        // Si tu entidad tiene fechaRegistro, Doctrine suele ponerla en el constructor o PrePersist,
        // si no, podrías hacer $usuario->setFechaRegistro(new \DateTime());

        try {
            $entityManager->persist($usuario);
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            // Capturamos el error específico de SQL Server cuando el correo ya existe
            return $this->json(
                ['error' => 'El correo o el Google ID ya están registrados en el sistema.'],
                Response::HTTP_CONFLICT // 409 Conflict
            );
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error interno al guardar: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->json($usuario, Response::HTTP_CREATED, [], ['groups' => 'usuario:read']);
    }

    // ========================================================================
    // 3. ELIMINAR USUARIO (DELETE) - Usando Stored Procedure
    // ========================================================================
    #[Route('/usuarios/{id}', name: 'borrar_usuario', methods: ['DELETE'])]
    #[OA\Response(response: 200, description: 'Usuario eliminado (Soft Delete aplicado)')]
    #[OA\Response(response: 404, description: 'Usuario no encontrado')]
    public function delete(
        int $id,
        UsuarioRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        // Primero verificamos si existe (es rápido con Doctrine)
        $usuario = $repo->find($id);
        if (!$usuario) {
            return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // USAMOS TU SP DEFINIDO EN SQL SERVER
        // SP_SoftDelete_Usuario se encarga de:
        // 1. Poner deleted_at = GETDATE()
        // 2. Poner estado_cuenta = 'Bloqueada'
        try {
            $sql = 'EXEC SP_SoftDelete_Usuario @id_usuario = :id';
            $em->getConnection()->executeStatement($sql, ['id' => $id]);

            return $this->json(
                ['mensaje' => 'Usuario eliminado correctamente (Soft Delete aplicado vía SP)'],
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al ejecutar el procedimiento de borrado: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    // ========================================================================
    // 4. SUBIR FOTO PERFIL (POST)
    // ========================================================================
    #[Route('/usuarios/{id}/imagen', name: 'upload_imagen_usuario', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'url_imagen', type: 'string', description: 'Base64 image string')
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Imagen de perfil actualizada')]
    public function uploadImagen(
        int $id,
        Request $request,
        UsuarioRepository $usuarioRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $usuario = $usuarioRepo->find($id);
        if (!$usuario) {
            return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $urlImagen = $data['url_imagen'] ?? null;

        if (empty($urlImagen)) {
            return $this->json(['error' => 'Falta la imagen (base64)'], Response::HTTP_BAD_REQUEST);
        }

        // LOGICA BASE64
        if (preg_match('/^data:image\/(\w+);base64,/', $urlImagen, $type)) {
            $dataBase64 = substr($urlImagen, strpos($urlImagen, ',') + 1);
            $extension = strtolower($type[1]); // jpg, png, etc.

            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                return $this->json(['error' => 'Formato de imagen no soportado (solo jpg, png, gif, webp)'], Response::HTTP_BAD_REQUEST);
            }

            $decodedData = base64_decode($dataBase64);
            if ($decodedData === false) {
                return $this->json(['error' => 'Error al decodificar Base64'], Response::HTTP_BAD_REQUEST);
            }

            // Validar Tamaño (Max 2MB)
            if (strlen($decodedData) > 2 * 1024 * 1024) {
                return $this->json(['error' => 'La imagen supera el tamaño máximo permitido (2MB)'], Response::HTTP_BAD_REQUEST);
            }

            // Crear directorio si no existe (Usuarios/Perfiles)
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/perfiles';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Guardar archivo
            $filename = uniqid('usr_' . $id . '_') . '.' . $extension;
            try {
                file_put_contents($uploadDir . '/' . $filename, $decodedData);

                $relativePath = '/uploads/perfiles/' . $filename;

                // Guardar en ENTIDAD USUARIO
                $usuario->setImgPerfil($relativePath);
                $em->flush();

                return $this->json([
                    'mensaje' => 'Foto de perfil actualizada correctamente',
                    'url' => $relativePath
                ], Response::HTTP_OK);
            } catch (\Exception $e) {
                return $this->json(['error' => 'No se pudo guardar la imagen en el servidor'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        return $this->json(['error' => 'Formato de imagen inválido (debe ser base64)'], Response::HTTP_BAD_REQUEST);
    }
}
