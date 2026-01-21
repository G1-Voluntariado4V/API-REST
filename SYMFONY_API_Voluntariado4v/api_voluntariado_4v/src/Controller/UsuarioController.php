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

use Symfony\Component\Security\Http\Attribute\IsGranted; // Seguridad

#[Route('', name: 'api_')]
#[OA\Tag(name: 'Usuarios', description: 'Gestión administrativa de usuarios')]
final class UsuarioController extends AbstractController
{

    // ========================================================================
    #[OA\Response(
        response: 200,
        description: 'Listado de usuarios activos (Desde Vista SQL)',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(property: 'id_usuario', type: 'integer', example: 1),
                new OA\Property(property: 'correo', type: 'string', example: 'usuario@test.com'),
                new OA\Property(property: 'nombre_rol', type: 'string', example: 'Voluntario'),
                new OA\Property(property: 'estado_cuenta', type: 'string', example: 'Activa')
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
    // 4. SUBIR/ACTUALIZAR FOTO PERFIL (POST) - multipart/form-data
    // ========================================================================
    #[Route('/usuarios/{id}/imagen', name: 'upload_imagen_usuario', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(
                        property: 'imagen',
                        type: 'string',
                        format: 'binary',
                        description: 'Archivo de imagen (jpg, jpeg, png, webp). Máximo 5MB.'
                    )
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Imagen de perfil actualizada correctamente')]
    #[OA\Response(response: 400, description: 'Error en la validación del archivo')]
    #[OA\Response(response: 404, description: 'Usuario no encontrado')]
    #[OA\Response(response: 500, description: 'Error de escritura en disco')]
    public function uploadImagen(
        int $id,
        Request $request,
        UsuarioRepository $usuarioRepo,
        EntityManagerInterface $em,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%uploads_directory%')] string $uploadsDirectory
    ): JsonResponse {
        // 1. Buscar al usuario
        $usuario = $usuarioRepo->find($id);
        if (!$usuario) {
            return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // 2. Recoger el archivo del campo 'imagen'
        $file = $request->files->get('imagen');
        if (!$file) {
            return $this->json(['error' => 'No se ha enviado ningún archivo en el campo "imagen"'], Response::HTTP_BAD_REQUEST);
        }

        // 3. Validar extensión
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($extension, $allowedExtensions)) {
            return $this->json([
                'error' => 'Formato de imagen no soportado. Permitidos: ' . implode(', ', $allowedExtensions)
            ], Response::HTTP_BAD_REQUEST);
        }

        // 4. Validar tamaño (máximo 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file->getSize() > $maxSize) {
            return $this->json(['error' => 'La imagen supera el tamaño máximo permitido (5MB)'], Response::HTTP_BAD_REQUEST);
        }

        // 5. Preparar directorio de destino (/public/uploads/usuarios)
        $targetDirectory = $uploadsDirectory . '/usuarios';
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0777, true);
        }

        // 6. Generar nombre único y mover el archivo
        $filename = uniqid('usr_' . $id . '_') . '.' . $extension;
        try {
            $file->move($targetDirectory, $filename);
        } catch (\Symfony\Component\HttpFoundation\File\Exception\FileException $e) {
            return $this->json([
                'error' => 'Error al guardar la imagen en el servidor: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // 7. Eliminar imagen anterior si existía
        $oldImage = $usuario->getImgPerfil();
        if ($oldImage) {
            $oldPath = $uploadsDirectory . '/usuarios/' . $oldImage;
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        // 8. Actualizar la entidad con el nuevo nombre de archivo
        $usuario->setImgPerfil($filename);
        $em->persist($usuario);
        $em->flush();

        return $this->json([
            'mensaje' => 'Foto de perfil actualizada correctamente',
            'img_perfil' => $filename,
            'img_url' => '/uploads/usuarios/' . $filename
        ], Response::HTTP_OK);
    }

    // ========================================================================
    // 5. CAMBIAR ROL DE USUARIO (PUT) - Solo para Coordinadores
    // ========================================================================
    #[Route('/usuarios/{id}/rol', name: 'cambiar_rol_usuario', methods: ['PUT'])]
    #[IsGranted('ROLE_COORDINADOR', message: 'Solo los coordinadores pueden cambiar roles de usuario')]
    #[OA\RequestBody(
        description: 'Nuevo rol para el usuario',
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id_rol', type: 'integer', example: 2, description: '1=Coordinador, 2=Organización, 3=Voluntario')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Rol actualizado correctamente',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'mensaje', type: 'string'),
                new OA\Property(property: 'usuario', type: 'object'),
                new OA\Property(property: 'advertencia', type: 'string', nullable: true)
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Usuario o Rol no encontrado')]
    #[OA\Response(response: 400, description: 'Datos inválidos')]
    public function cambiarRol(
        int $id,
        Request $request,
        UsuarioRepository $usuarioRepo,
        RolRepository $rolRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        // 1. Buscar al usuario
        $usuario = $usuarioRepo->find($id);
        if (!$usuario) {
            return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // 2. Obtener el nuevo id_rol del request
        $data = json_decode($request->getContent(), true);
        if (!isset($data['id_rol']) || !is_numeric($data['id_rol'])) {
            return $this->json(['error' => 'El campo id_rol es obligatorio y debe ser numérico'], Response::HTTP_BAD_REQUEST);
        }

        $nuevoIdRol = (int) $data['id_rol'];

        // 3. Buscar el nuevo rol
        $nuevoRol = $rolRepo->find($nuevoIdRol);
        if (!$nuevoRol) {
            return $this->json(['error' => 'Rol no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // 4. Verificar si el rol destino requiere perfil complementario
        $advertencia = null;
        $nombreRol = strtolower($nuevoRol->getNombre());

        if ($nombreRol === 'voluntario') {
            // Verificar si existe el registro en VOLUNTARIO
            $voluntario = $em->getRepository(\App\Entity\Voluntario::class)->find($id);
            if (!$voluntario) {
                $advertencia = 'El usuario no tiene perfil de Voluntario completado. Deberá completar sus datos personales.';
            }
        } elseif ($nombreRol === 'organización' || $nombreRol === 'organizacion') {
            // Verificar si existe el registro en ORGANIZACION
            $organizacion = $em->getRepository(\App\Entity\Organizacion::class)->find($id);
            if (!$organizacion) {
                $advertencia = 'El usuario no tiene perfil de Organización completado. Deberá completar los datos de su empresa/ONG.';
            }
        }

        // 5. Actualizar el rol
        $usuario->setRol($nuevoRol);
        $em->persist($usuario);
        $em->flush();

        // 6. Preparar respuesta
        $respuesta = [
            'mensaje' => 'Rol actualizado correctamente',
            'usuario' => [
                'id' => $usuario->getId(),
                'correo' => $usuario->getCorreo(),
                'rol' => $nuevoRol->getNombre(),
                'estado_cuenta' => $usuario->getEstadoCuenta()
            ]
        ];

        if ($advertencia) {
            $respuesta['advertencia'] = $advertencia;
        }

        return $this->json($respuesta, Response::HTTP_OK);
    }
}
