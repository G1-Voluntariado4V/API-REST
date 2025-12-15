<?php

namespace App\Controller;

use App\Repository\UsuarioRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/auth', name: 'api_auth_')]
#[OA\Tag(name: 'Autenticación', description: 'Login y gestión de acceso')]
final class AuthController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'google_id', type: 'string', example: '1122334455'),
                new OA\Property(property: 'email', type: 'string', example: 'usuario@gmail.com')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Login exitoso',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id_usuario', type: 'integer'),
                new OA\Property(property: 'rol', type: 'string'),
                new OA\Property(property: 'estado', type: 'string'),
                new OA\Property(property: 'token', type: 'string', description: 'Aquí iría el JWT en un futuro')
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Usuario no registrado (Redirigir a Registro)')]
    #[OA\Response(response: 403, description: 'Cuenta bloqueada o pendiente de aprobación')]
    public function login(Request $request, UsuarioRepository $usuarioRepository): JsonResponse
    {
        // 1. Recibir datos
        $data = json_decode($request->getContent(), true);
        $googleId = $data['google_id'] ?? null;
        $email = $data['email'] ?? null;

        if (!$googleId) {
            return $this->json(['mensaje' => 'Falta el google_id'], Response::HTTP_BAD_REQUEST);
        }

        // 2. Buscar Usuario (Prioridad ID > Email)
        $usuario = $usuarioRepository->findOneBy(['googleId' => $googleId]);

        if (!$usuario && $email) {
            $usuario = $usuarioRepository->findOneBy(['correo' => $email]);
        }

        // 3. Semáforo de Acceso

        // ROJO: No existe -> 404 (Frontend redirige a Registro)
        if (!$usuario) {
            return $this->json(['mensaje' => 'Usuario no registrado. Requiere registro.'], Response::HTTP_NOT_FOUND);
        }

        // NEGRO: Cuenta eliminada o bloqueada -> 403 Forbidden
        if ($usuario->getDeletedAt() !== null || $usuario->getEstadoCuenta() === 'Bloqueada') {
            return $this->json([
                'error' => 'Acceso denegado',
                'mensaje' => 'Tu cuenta ha sido bloqueada o eliminada. Contacta con soporte.'
            ], Response::HTTP_FORBIDDEN);
        }

        // AMARILLO: Cuenta pendiente (Organizaciones) -> 403 Forbidden (con mensaje amable)
        if ($usuario->getEstadoCuenta() === 'Pendiente') {
            return $this->json([
                'error' => 'Cuenta no verificada',
                'mensaje' => 'Tu solicitud está en revisión. Te avisaremos cuando se active.'
            ], Response::HTTP_FORBIDDEN);
        }

        // VERDE: Acceso concedido
        return $this->json([
            'id_usuario' => $usuario->getId(),
            'google_id'  => $usuario->getGoogleId(),
            'correo'     => $usuario->getCorreo(),
            'rol'        => $usuario->getRol() ? $usuario->getRol()->getNombre() : 'User',
            'estado'     => $usuario->getEstadoCuenta()
        ], Response::HTTP_OK);
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'role', type: 'string', example: 'volunteer|organizer'),
                new OA\Property(property: 'google_id', type: 'string'),
                new OA\Property(property: 'email', type: 'string'),
                // Campos comunes o específicos...
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Usuario creado exitosamente')]
    #[OA\Response(response: 400, description: 'Datos inválidos o faltantes')]
    public function register(Request $request, UsuarioRepository $usuarioRepository, \Doctrine\ORM\EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // 1. Validar Datos Básicos
        $rolType = $data['role'] ?? null; // 'volunteer' | 'organizer'
        $googleId = $data['google_id'] ?? null;
        $email = $data['email'] ?? null;

        if (!$rolType || !$googleId || !$email) {
            return $this->json(['mensaje' => 'Faltan datos obligatorios (role, google_id, email)'], Response::HTTP_BAD_REQUEST);
        }

        // 2. Verificar duplicados
        $existingUser = $usuarioRepository->findOneBy(['googleId' => $googleId]);
        if ($existingUser) {
            return $this->json(['mensaje' => 'El usuario ya existe'], Response::HTTP_CONFLICT); // 409
        }

        // 3. Crear Usuario Base
        $usuario = new \App\Entity\Usuario();
        $usuario->setGoogleId($googleId);
        $usuario->setCorreo($email);
        $usuario->setFechaRegistro(new \DateTime());

        // Asignar Rol
        $rolRepository = $em->getRepository(\App\Entity\Rol::class);
        $rol = null;

        if ($rolType === 'volunteer') {
            $rol = $rolRepository->findOneBy(['nombre' => 'Voluntario']);
            $usuario->setEstadoCuenta('Activa');
        } elseif ($rolType === 'organizer') {
            $rol = $rolRepository->findOneBy(['nombre' => 'Organización']);
            if (!$rol) $rol = $rolRepository->findOneBy(['nombre' => 'Organizador']);
            $usuario->setEstadoCuenta('Pendiente');
        } else {
            return $this->json(['mensaje' => 'Rol inválido'], Response::HTTP_BAD_REQUEST);
        }

        if (!$rol) {
            return $this->json(['mensaje' => 'Error de configuración: Rol no encontrado en BD'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $usuario->setRol($rol);
        $em->persist($usuario);

        // FLUSH 1: Guardar Usuario para obtener ID
        $em->flush();

        // 4. Crear Entidad Específica
        if ($rolType === 'volunteer') {
            $voluntario = new \App\Entity\Voluntario();
            $voluntario->setNombre($data['nombre'] ?? '');
            $voluntario->setApellidos($data['apellidos'] ?? '');
            $voluntario->setDni($data['dni'] ?? '');
            $voluntario->setTelefono($data['telefono'] ?? '');
            if (isset($data['fecha_nac'])) {
                $voluntario->setFechaNac(new \DateTime($data['fecha_nac']));
            }
            $voluntario->setUsuario($usuario); // Esto asignará el ID manualmente

            if (!empty($data['id_curso_actual'])) {
                $curso = $em->getRepository(\App\Entity\Curso::class)->find($data['id_curso_actual']);
                if ($curso) $voluntario->setCursoActual($curso); // Nota: setCursoActual (según la entidad)
            }
            $em->persist($voluntario);
        } elseif ($rolType === 'organizer') {
            $organizacion = new \App\Entity\Organizacion();
            $organizacion->setNombre($data['nombre'] ?? 'Sin Nombre');
            $organizacion->setCif($data['cif'] ?? '');
            $organizacion->setDireccion($data['direccion'] ?? null);
            $organizacion->setTelefono($data['telefono'] ?? null);
            $organizacion->setSitioWeb($data['sitio_web'] ?? null);
            $organizacion->setDescripcion($data['descripcion'] ?? null);
            $organizacion->setUsuario($usuario); // Esto asignará el ID manualmente

            $em->persist($organizacion);
        }

        // FLUSH 2: Guardar entidad dependiente
        $em->flush();

        return $this->json([
            'mensaje' => 'Registro exitoso',
            'id_usuario' => $usuario->getId(),
            'rol' => $rol->getNombre()
        ], Response::HTTP_CREATED);
    }
}
